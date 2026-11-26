<?php

declare(strict_types=1);

namespace App\Growth;

use RuntimeException;

/**
 * A provider failed. The message is safe to show in the dashboard: it never contains a URL, a token or a header,
 * only what went wrong and what to check.
 */
final class SourceError extends RuntimeException {}
