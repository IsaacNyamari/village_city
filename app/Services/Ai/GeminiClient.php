<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Google Gemini (Generative Language API) client for structured JSON output.
 *
 * - The API key is read from config('services.gemini.key') (.env only) and sent in a header,
 *   never in a URL, and is never logged.
 * - Models are tried in the configured order; a model that is missing (404) is skipped, and
 *   transient errors (429/5xx/timeouts) are retried once before falling back to the next model.
 */
class GeminiClient
{
    protected const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /** Model that produced the last successful response. */
    public ?string $lastModel = null;

    public function configured(): bool
    {
        return filled(config('services.gemini.key')) && $this->models() !== [];
    }

    /** @return list<string> */
    public function models(): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', (string) config('services.gemini.models', ''))))));
    }

    /**
     * Ask for JSON matching a response schema (Gemini schema types in upper case: OBJECT, ARRAY, STRING, INTEGER, BOOLEAN).
     *
     * @return array<mixed>
     *
     * @throws GeminiException
     */
    public function json(string $prompt, array $schema, ?string $system = null, float $temperature = 0.5): array
    {
        if (! $this->configured()) {
            throw new GeminiException('The AI assistant is not configured. Add GEMINI_API_KEY to the .env file and clear the config cache.');
        }

        $payload = array_filter([
            'systemInstruction' => $system ? ['parts' => [['text' => $system]]] : null,
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
                'temperature' => $temperature,
                'maxOutputTokens' => 4096,
            ],
        ]);

        $failures = [];
        // Overall budget so slow retries/fallbacks cannot hang an admin request indefinitely.
        $deadline = microtime(true) + (int) config('services.gemini.deadline', 55);

        foreach ($this->models() as $model) {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                if (microtime(true) > $deadline) {
                    break 2;
                }

                try {
                    $response = Http::withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
                        ->acceptJson()->connectTimeout(10)->timeout((int) config('services.gemini.timeout', 40))
                        ->post(sprintf(self::ENDPOINT, $model), $payload);
                } catch (ConnectionException) {
                    $failures[] = "{$model}: connection problem";
                    usleep($this->backoff() * $attempt);

                    continue;
                }

                $status = $response->status();

                if ($response->successful()) {
                    $this->lastModel = $model;

                    return $this->parse($response->json() ?? []);
                }

                $message = (string) data_get($response->json(), 'error.message', '');

                if ($status === 404) {
                    $failures[] = "{$model}: model not found";

                    break; // try the next model
                }

                if (in_array($status, [401, 403], true) || ($status === 400 && stripos($message, 'api key') !== false)) {
                    throw new GeminiException('Google rejected the Gemini API key. Check GEMINI_API_KEY in the .env file.');
                }

                if ($status === 400) {
                    throw new GeminiException('Gemini could not process that request: '.mb_substr($message, 0, 180));
                }

                $failures[] = "{$model}: HTTP {$status}";

                if (in_array($status, [429, 500, 502, 503, 504], true)) {
                    usleep($this->backoff() * $attempt);

                    continue;
                }

                break;
            }
        }

        // Only model names and status codes are logged — never the key, prompt or response text.
        Log::warning('Gemini request failed on every model', ['failures' => $failures]);

        throw new GeminiException('The AI service is busy or unavailable right now. Please try again in a moment.');
    }

    /** Back-off between retries, in microseconds (configurable so tests do not sleep). */
    protected function backoff(): int
    {
        return max(0, (int) config('services.gemini.backoff_ms', 700)) * 1000;
    }

    /** @return array<mixed> */
    protected function parse(array $body): array
    {
        if ($reason = data_get($body, 'promptFeedback.blockReason')) {
            throw new GeminiException('Gemini declined to answer this request ('.$reason.').');
        }

        $text = (string) data_get($body, 'candidates.0.content.parts.0.text', '');
        $finish = (string) data_get($body, 'candidates.0.finishReason', '');

        if ($text === '' || in_array($finish, ['SAFETY', 'RECITATION'], true)) {
            throw new GeminiException('Gemini returned no usable answer. Try again or adjust your keywords.');
        }

        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)));
        $data = json_decode($text, true);

        if (! is_array($data)) {
            throw new GeminiException('Gemini returned an unexpected answer. Please try again.');
        }

        return $data;
    }
}
