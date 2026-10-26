<?php
namespace App\Services;

/**
 * Renders quotation/booking PDFs to a file on disk by capturing the exact
 * HTML their *_pdf.php counterparts already produce for their browser-print
 * view — via an isolated-scope include (their own $_GET, wrapped in a
 * closure, so local variables can't collide with the caller's) — then
 * running it through dompdf. One implementation shared by
 * api/quotation_email.php, api/book.php, and api/convert_quotation.php,
 * instead of repeated copies of the same capture-and-strip logic.
 *
 * Pricing (quotation_pdf.php / booking_pdf.php) and the day-by-day
 * itinerary (quotation_itinerary_pdf.php / booking_itinerary_pdf.php) are
 * deliberately separate documents — two focused PDFs the client can open
 * independently, rather than one long combined one.
 *
 * All target files define SZV_PDF_INTERNAL_RENDER before including them,
 * which skips their normal admin-session requirement — safe here because
 * this class is only ever invoked from trusted server-side code, never
 * from a value an HTTP request controls.
 */
class PdfRenderer
{
    /** @return array{path:string,name:string}|null */
    public static function renderQuotation(int $quotationId, string $fileName): ?array
    {
        return self::render(__DIR__ . '/../../api/quotation_pdf.php', ['id' => $quotationId], $fileName);
    }

    /** @return array{path:string,name:string}|null */
    public static function renderQuotationItinerary(int $quotationId, string $fileName): ?array
    {
        return self::render(__DIR__ . '/../../api/quotation_itinerary_pdf.php', ['id' => $quotationId], $fileName);
    }

    /**
     * Same document as renderQuotation(), relabeled INVOICE/PAID IN FULL —
     * quotation_pdf.php itself re-verifies the linked booking is actually
     * fully_paid before honoring this, so it can't be used to misrepresent
     * an unpaid booking.
     * @return array{path:string,name:string}|null
     */
    public static function renderQuotationInvoice(int $quotationId, string $fileName): ?array
    {
        return self::render(__DIR__ . '/../../api/quotation_pdf.php', ['id' => $quotationId, 'invoice' => 1], $fileName);
    }

    /** @return array{path:string,name:string}|null */
    public static function renderBooking(string $bookingRef, string $fileName): ?array
    {
        return self::render(__DIR__ . '/../../api/booking_pdf.php', ['ref' => $bookingRef], $fileName);
    }

    /**
     * Same document as renderBooking(), relabeled INVOICE/PAID IN FULL —
     * booking_pdf.php itself re-verifies payment_status is fully_paid
     * before honoring this.
     * @return array{path:string,name:string}|null
     */
    public static function renderBookingInvoice(string $bookingRef, string $fileName): ?array
    {
        return self::render(__DIR__ . '/../../api/booking_pdf.php', ['ref' => $bookingRef, 'invoice' => 1], $fileName);
    }

    /** @return array{path:string,name:string}|null */
    public static function renderBookingItinerary(string $bookingRef, string $fileName): ?array
    {
        return self::render(__DIR__ . '/../../api/booking_itinerary_pdf.php', ['ref' => $bookingRef], $fileName);
    }

    /** @return array{path:string,name:string}|null */
    public static function renderVoucher(string $bookingRef, string $fileName): ?array
    {
        return self::render(__DIR__ . '/../../api/voucher_pdf.php', ['ref' => $bookingRef], $fileName);
    }

    private static function render(string $file, array $getParams, string $fileName): ?array
    {
        if (!class_exists('\Dompdf\Dompdf')) {
            error_log('[PdfRenderer] dompdf not installed — skipping PDF generation.');
            return null;
        }

        if (!defined('SZV_PDF_INTERNAL_RENDER')) {
            define('SZV_PDF_INTERNAL_RENDER', true);
        }

        // ARCH-4: $_GET is swapped out and an output buffer opened right
        // below, before the included script runs; both MUST be restored/
        // closed even if that include throws partway through — previously
        // only the success path did either ($_GET = $prevGet and
        // ob_get_clean() below), so an exception left the buffer open and
        // $_GET permanently pointing at $getParams for the rest of the
        // request. $obLevelBefore pins exactly how many buffers were open
        // before this call, so the finally block below only ever cleans up
        // a buffer this call itself opened, never one it didn't.
        $prevGet       = $_GET;
        $obLevelBefore = ob_get_level();

        try {
            $_GET = $getParams;
            ob_start();
            (function () use ($file) { include $file; })();
            $html = ob_get_clean();

            // dompdf tries to fetch AND cache Google Fonts locally; a common
            // failure (fwrite(): $stream must be of type resource, false
            // given) happens when its font-cache directory isn't writable —
            // the pages' own CSS already falls back to Georgia/Arial without
            // the web font, so dropping the @import is a safe, low-risk fix.
            $html = preg_replace('/@import\s+url\([^)]*fonts\.googleapis\.com[^)]*\)\s*;?/i', '', $html);

            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true); // needed to fetch the logo image URL
            $cacheDir = sys_get_temp_dir() . '/szv_dompdf_cache';
            if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0775, true); }
            $options->set('fontCache', $cacheDir);
            $options->set('tempDir', sys_get_temp_dir());

            $pdf = new \Dompdf\Dompdf($options);
            $pdf->loadHtml($html);
            $pdf->setPaper('A4', 'portrait');
            $pdf->render();

            $dir = __DIR__ . '/../../storage/uploads/quotations';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $safeName = preg_replace('/[^A-Za-z0-9_-]/', '', $fileName) . '.pdf';
            $path     = $dir . '/' . $safeName;
            file_put_contents($path, $pdf->output());

            return ['path' => $path, 'name' => $safeName];
        } catch (\Throwable $e) {
            error_log('[PdfRenderer] Failed to render ' . $file . ': ' . $e->getMessage());
            return null;
        } finally {
            while (ob_get_level() > $obLevelBefore) { ob_end_clean(); }
            $_GET = $prevGet;
        }
    }
}
