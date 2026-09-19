<?php
namespace App\Helpers;

/**
 * Renders an itinerary's day-by-day plan as read-only cards matching the
 * visual arrangement of the admin/itineraries.php editor — a "Day N" +
 * title header, then one colour-coded box per category (Accommodation /
 * Vehicle / Park) exactly like the editor's blue/green/amber input boxes,
 * just showing values instead of input fields.
 *
 * Previously api/itinerary_pdf.php and api/quotation_pdf.php each carried
 * their own byte-for-byte copy of a much plainer 2-column label/value
 * layout that looked nothing like the editor; api/booking_pdf.php and
 * api/booking_email.php used a third, table-only layout again. One
 * implementation now, reused everywhere a day-by-day plan is shown.
 */
class ItineraryDayCards
{
    /**
     * @param bool $useEmoji Real browser HTML (views/packages.php) renders
     *        emoji fine. dompdf (every PDF context) does not — confirmed by
     *        actually rendering a test PDF: emoji come out as literal "?"
     *        glyphs, not just a font-fallback square. Leave this false for
     *        any PDF/print context.
     */
    public static function render(array $days, bool $useEmoji = false): string
    {
        $html = '';
        foreach ($days as $day) {
            $dayNum   = $day['day']         ?? '?';
            $dayTitle = htmlspecialchars($day['title']       ?? '');
            $dayDesc  = $day['description'] ?? '';
            $location = htmlspecialchars($day['location']    ?? '');
            $accName  = htmlspecialchars($day['accommodation_name'] ?? '');
            $vehName  = htmlspecialchars($day['vehicle_name']       ?? '');
            $parkName = htmlspecialchars($day['park_fee_name']      ?? '');
            $acts     = $day['activities'] ?? [];
            $actNames = array_filter(array_column($acts, 'name'));
            $accAlts  = array_filter(array_map('htmlspecialchars', $day['accommodation_alternatives'] ?? []));

            $accIcon  = $useEmoji ? '🏕 ' : '';
            $vehIcon  = $useEmoji ? '🚙 ' : '';
            $parkIcon = $useEmoji ? '🏞 ' : '';

            $boxes = '';
            if ($accName) {
                $accAltHtml = $accAlts ? '<div class="box-alt">or similar: ' . implode(', ', $accAlts) . '</div>' : '';
                $boxes .= '<div class="day-box box-acc"><p class="box-lbl">' . $accIcon . 'Accommodation</p><div class="box-val">' . $accName . '</div>' . $accAltHtml . '</div>';
            }
            if ($vehName) {
                $boxes .= '<div class="day-box box-veh"><p class="box-lbl">' . $vehIcon . 'Vehicle</p><div class="box-val">' . $vehName . '</div></div>';
            }
            if ($parkName) {
                $boxes .= '<div class="day-box box-park"><p class="box-lbl">' . $parkIcon . 'Park</p><div class="box-val">' . $parkName . '</div></div>';
            }

            $html .= '<div class="day-card">'
                . '<div class="day-hdr">'
                .   '<span class="day-num">Day ' . htmlspecialchars((string)$dayNum) . '</span>'
                .   '<span class="day-title">' . $dayTitle . ($location ? ' &middot; ' . $location : '') . '</span>'
                . '</div>'
                . '<div class="day-body">'
                .   ($dayDesc ? '<div class="day-desc">' . nl2br(htmlspecialchars($dayDesc)) . '</div>' : '')
                .   ($boxes ? '<div class="day-boxes">' . $boxes . '</div>' : '')
                .   ($actNames ? '<div class="day-activities">&#10022; ' . implode(' &middot; ', array_map('htmlspecialchars', $actNames)) . '</div>' : '')
                . '</div>'
                . '</div>';
        }
        return $html;
    }

    /**
     * CSS for render() — include once per page/PDF.
     *
     * Every weight below is 400/normal or 700/bold on purpose — confirmed
     * by rendering actual test PDFs that dompdf's font matching silently
     * breaks on in-between numeric weights like 500/600: it can't find a
     * matching DejaVu Sans variant, falls back to an unrelated serif font,
     * and that fallback is missing glyphs like → and ✦, printing "?" in
     * their place with no warning. 700/bold hits DejaVu Sans Bold and
     * renders correctly. Don't "smooth" these to 600 for a lighter look.
     */
    public static function css(): string
    {
        return "
        .day-card{border:1px solid #e5e7eb;margin-bottom:1rem;page-break-inside:avoid}
        .day-hdr{background:#0a0a0a;color:#fff;padding:.6rem .9rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.35rem}
        .day-num{font-family:'Playfair Display',Georgia,serif;font-size:1.1rem}
        .day-title{font-size:.85rem;font-weight:700}
        .day-body{padding:.85rem .9rem}
        .day-desc{font-size:.82rem;color:#374151;line-height:1.75;margin-bottom:.65rem}
        .day-boxes{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.6rem;margin-bottom:.65rem}
        .day-box{border:1px solid #e5e7eb;padding:.65rem .75rem}
        .day-box.box-acc{background:#eff6ff}
        .day-box.box-veh{background:#f0fdf4}
        .day-box.box-park{background:#fef3e2}
        .box-lbl{font-size:.56rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;margin:0 0 .35rem}
        .box-acc .box-lbl{color:#1d4ed8}
        .box-veh .box-lbl{color:#166534}
        .box-park .box-lbl{color:#92400e}
        .box-val{font-size:.8rem;font-weight:700;color:#0a0a0a}
        .box-alt{font-size:.66rem;font-weight:400;color:#6b7280;margin-top:.2rem}
        .day-activities{margin-top:.5rem;font-size:.78rem;color:#374151}
        @media screen and (max-width:480px){
          .day-hdr{padding:.55rem .7rem}
          .day-num{font-size:1rem}
          .day-title{font-size:.8rem}
          .day-body{padding:.7rem .75rem}
          .day-box{padding:.55rem .6rem}
        }
        ";
    }

    /**
     * Table-based equivalent for HTML emails, where flexbox/grid support
     * is unreliable across mail clients — bulletproof-HTML-email pattern:
     * nested tables with inline styles instead of a stylesheet.
     */
    public static function renderEmailSafe(array $days): string
    {
        $html = '';
        foreach ($days as $day) {
            $dayNum   = htmlspecialchars((string)($day['day'] ?? '?'));
            $dayTitle = htmlspecialchars($day['title']       ?? '');
            $dayDesc  = $day['description'] ?? '';
            $location = htmlspecialchars($day['location']    ?? '');
            $accName  = htmlspecialchars($day['accommodation_name'] ?? '');
            $vehName  = htmlspecialchars($day['vehicle_name']       ?? '');
            $parkName = htmlspecialchars($day['park_fee_name']      ?? '');
            $acts     = $day['activities'] ?? [];
            $actNames = array_filter(array_column($acts, 'name'));
            $accAlts  = array_filter(array_map('htmlspecialchars', $day['accommodation_alternatives'] ?? []));

            $boxCell = function (string $label, string $value, string $bg, string $labelColor, string $sub = ''): string {
                return '<td width="33%" valign="top" style="padding:0 4px 0 0">'
                    . '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $bg . ';border:1px solid #e5e7eb">'
                    . '<tr><td style="padding:8px 10px">'
                    . '<div style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:' . $labelColor . ';margin-bottom:4px">' . $label . '</div>'
                    . '<div style="font-size:12px;font-weight:600;color:#0a0a0a">' . $value . '</div>'
                    . ($sub ? '<div style="font-size:9px;color:#6b7280;margin-top:2px">' . $sub . '</div>' : '')
                    . '</td></tr></table></td>';
            };

            $boxes = '';
            if ($accName) {
                $accSub = $accAlts ? 'or similar: ' . implode(', ', $accAlts) : '';
                $boxes .= $boxCell('Accommodation', $accName, '#eff6ff', '#1d4ed8', $accSub);
            }
            if ($vehName) $boxes .= $boxCell('Vehicle', $vehName, '#f0fdf4', '#166534');
            if ($parkName) $boxes .= $boxCell('Park', $parkName, '#fef3e2', '#92400e');

            $html .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e5e7eb;margin-bottom:14px">'
                . '<tr><td style="background:#0a0a0a;color:#fff;padding:10px 14px">'
                .   '<span style="font-size:15px;font-family:Georgia,serif">Day ' . $dayNum . '</span>'
                .   '<span style="font-size:13px;font-weight:600;margin-left:10px">' . $dayTitle . ($location ? ' &middot; ' . $location : '') . '</span>'
                . '</td></tr>'
                . '<tr><td style="padding:12px 14px">'
                .   ($dayDesc ? '<div style="font-size:12px;color:#374151;line-height:1.6;margin-bottom:10px">' . nl2br(htmlspecialchars($dayDesc)) . '</div>' : '')
                .   ($boxes ? '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' . $boxes . '</tr></table>' : '')
                .   ($actNames ? '<div style="margin-top:8px;font-size:12px;color:#374151">&#10022; ' . implode(' &middot; ', array_map('htmlspecialchars', $actNames)) . '</div>' : '')
                . '</td></tr>'
                . '</table>';
        }
        return $html;
    }
}
