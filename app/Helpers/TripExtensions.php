<?php
namespace App\Helpers;

/**
 * Per-package trip-extension suggestions — one source of truth shared by
 * the Packages page (a plain sentence) and the booking form (a
 * structured dropdown). Options are worded around what's NOT already in
 * that itinerary, so a package never "extends" into a destination it
 * already includes (e.g. Zanzibar is never offered on the one package
 * that already visits Zanzibar).
 */
class TripExtensions
{
    private const DATA = [
        'classic-safari' => [
            'line'    => 'Extendable — ask us about adding days in Ruaha, Selous or Zanzibar.',
            'options' => [
                'ruaha'    => 'Add Ruaha National Park (+3–4 days)',
                'selous'   => 'Add Selous / Nyerere Game Reserve (+3 days)',
                'zanzibar' => 'Add Zanzibar beach days (+3–5 days)',
            ],
        ],
        'luxury-safari' => [
            'line'    => 'Extendable — ask us about adding days in Ruaha, Selous or Zanzibar.',
            'options' => [
                'ruaha'    => 'Add Ruaha National Park (+3–4 days)',
                'selous'   => 'Add Selous / Nyerere Game Reserve (+3 days)',
                'zanzibar' => 'Add Zanzibar beach days (+3–5 days)',
            ],
        ],
        'comfort-safari' => [
            'line'    => 'Extendable — ask us about adding days in Ruaha, Selous or Zanzibar.',
            'options' => [
                'ruaha'    => 'Add Ruaha National Park (+3–4 days)',
                'selous'   => 'Add Selous / Nyerere Game Reserve (+3 days)',
                'zanzibar' => 'Add Zanzibar beach days (+3–5 days)',
            ],
        ],
        'safari-zanzibar' => [
            'line'    => 'Extendable — ask us about extra beach days in Zanzibar, or a side trip to Ruaha or Selous.',
            'options' => [
                'zanzibar_extra' => 'Extra Zanzibar beach days (+2–4 days)',
                'ruaha'          => 'Add Ruaha National Park (+3–4 days)',
                'selous'         => 'Add Selous / Nyerere Game Reserve (+3 days)',
            ],
        ],
        'cultural-safari' => [
            'line'    => 'Extendable — ask us about adding days in Ruaha, Selous or Zanzibar.',
            'options' => [
                'ruaha'    => 'Add Ruaha National Park (+3–4 days)',
                'selous'   => 'Add Selous / Nyerere Game Reserve (+3 days)',
                'zanzibar' => 'Add Zanzibar beach days (+3–5 days)',
            ],
        ],
        'short-safari' => [
            'line'    => 'Extendable — ask us about extending into the Serengeti, or adding Zanzibar, Ruaha or Selous.',
            'options' => [
                'serengeti' => 'Extend into the Serengeti (+2–3 days)',
                'zanzibar'  => 'Add Zanzibar beach days (+3–5 days)',
                'ruaha'     => 'Add Ruaha National Park (+3–4 days)',
                'selous'    => 'Add Selous / Nyerere Game Reserve (+3 days)',
            ],
        ],
    ];

    private const DEFAULT_LINE = 'Extendable — ask us about adding days in Ruaha, Selous or Zanzibar.';
    private const DEFAULT_OPTIONS = [
        'ruaha'    => 'Add Ruaha National Park (+3–4 days)',
        'selous'   => 'Add Selous / Nyerere Game Reserve (+3 days)',
        'zanzibar' => 'Add Zanzibar beach days (+3–5 days)',
    ];

    public static function lineFor(string $slug): string
    {
        return self::DATA[$slug]['line'] ?? self::DEFAULT_LINE;
    }

    /** @return array<string,string> code => label */
    public static function optionsFor(string $slug): array
    {
        return self::DATA[$slug]['options'] ?? self::DEFAULT_OPTIONS;
    }

    /** Every package's options, keyed by slug — for the booking form's JS. */
    public static function allOptionsJson(): string
    {
        $out = [];
        foreach (self::DATA as $slug => $d) {
            $out[$slug] = $d['options'];
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /** Resolve a submitted code back to its label, scoped to that package
     *  (a code only counts if it's actually offered for that slug — an
     *  unrecognised or tampered value is dropped, not stored as-is). */
    public static function labelFor(string $slug, string $code): ?string
    {
        return self::optionsFor($slug)[$code] ?? null;
    }
}
