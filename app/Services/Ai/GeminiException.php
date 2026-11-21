<?php

namespace App\Services\Ai;

use RuntimeException;

/** Thrown for any AI failure; the message is safe to show to administrators. */
class GeminiException extends RuntimeException {}
