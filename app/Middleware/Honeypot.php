<?php
namespace App\Middleware;

/**
 * A field real visitors never see or fill (hidden off-screen, not via
 * display:none — some bots skip display:none fields specifically) but
 * that generic form-spam scripts fill automatically. Any value in it
 * means the submission wasn't a real visitor.
 */
class Honeypot
{
    public const FIELD = 'website';

    public static function caught(): bool
    {
        return trim($_POST[self::FIELD] ?? '') !== '';
    }
}
