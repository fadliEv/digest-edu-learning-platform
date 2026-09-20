<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a video's audio source (Dyntube HLS) is not yet available for
 * transcription. Signals the transcript job to RETRY later rather than
 * treating the empty transcript as a permanent failure.
 */
class TranscriptNotReadyException extends RuntimeException
{
}
