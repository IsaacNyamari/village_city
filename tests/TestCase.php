<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Settings are cached statically per process; reset so tests never see each other's values.
        \App\Models\Setting::flushCache();
        // Tests must never reach external services (or spend the real Gemini quota).
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        config(['services.gemini.key' => null]);
    }
    //
}
