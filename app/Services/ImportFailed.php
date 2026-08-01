<?php

namespace App\Services;

use RuntimeException;

/**
 * A problem with the sheet as a whole — unreadable file, missing columns —
 * as opposed to a per-row validation error, which is collected and reported
 * alongside the others rather than thrown.
 */
class ImportFailed extends RuntimeException {}
