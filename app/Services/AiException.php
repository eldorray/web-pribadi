<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * A failure the user is allowed to read. Never carries the API key.
 */
class AiException extends RuntimeException
{
}
