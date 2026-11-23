<?php

namespace App\Exception;

/**
 * Raised when the vision model cannot reliably read product lines from the receipt image.
 */
final class ReceiptNotReadableException extends \RuntimeException
{
    public function __construct(
        string $message = 'We could not read this receipt. Please retake the photo with better lighting, hold the camera steady, and make sure the full receipt is in frame.',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
