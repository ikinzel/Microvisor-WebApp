<?php

namespace App\Tests\Support;

/**
 * Paths to committed receipt image fixtures for upload tests (no GD required).
 */
final class ReceiptTestImageFactory
{
    /** Sharp / readable receipt photo (Albert Heijn JPEG) for OCR + USDA mocks. */
    public static function sharpFixturePath(): string
    {
        return dirname(__DIR__).'/Fixtures/receipts/receipt_albertheijn.jpeg';
    }

    public static function sharpMimeType(): string
    {
        return 'image/jpeg';
    }

    /** Whole Foods–style English receipt photo (pairs with receipt_english.json). */
    public static function englishFixturePath(): string
    {
        return dirname(__DIR__).'/Fixtures/receipts/eng.jpg';
    }

    public static function englishMimeType(): string
    {
        return 'image/jpeg';
    }

    /** Low-detail image for blur rejection (when ext-gd is available). */
    public static function blurryFixturePath(): string
    {
        return dirname(__DIR__).'/Fixtures/receipts/blurry.png';
    }

    public static function blurryMimeType(): string
    {
        return 'image/png';
    }
}
