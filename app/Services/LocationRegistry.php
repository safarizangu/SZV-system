<?php
namespace App\Services;

/**
 * Auto-growing master lists of locations (safari destinations — parks,
 * towns, regions) and countries, used only to power autocomplete
 * <datalist> options wherever an admin types one of these values
 * (accommodations, park fees, itinerary day locations, booking/quotation
 * client country). There is no separate management page — touch*() is
 * called from every save handler that captures a location or country, so
 * the lists build themselves from real usage instead of needing to be
 * entered twice.
 */
class LocationRegistry
{
    /** Upserts a location name, optionally attaching/refreshing which country it's in. */
    public static function touchLocation(\PDO $db, ?string $name, ?string $country = null): void
    {
        $name = trim((string)$name);
        if ($name === '') return;
        $country = trim((string)$country);
        try {
            if ($country !== '') {
                $db->prepare("INSERT INTO locations (name, country) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE country = VALUES(country)")
                    ->execute([$name, $country]);
                self::touchCountry($db, $country);
            } else {
                $db->prepare("INSERT INTO locations (name) VALUES (?) ON DUPLICATE KEY UPDATE name = name")
                    ->execute([$name]);
            }
        } catch (\Exception $e) { error_log('[SZV LocationRegistry] '.$e->getMessage()); }
    }

    /** Upserts a country name. */
    public static function touchCountry(\PDO $db, ?string $name): void
    {
        $name = trim((string)$name);
        if ($name === '') return;
        try {
            $db->prepare("INSERT INTO countries (name) VALUES (?) ON DUPLICATE KEY UPDATE name = name")
                ->execute([$name]);
        } catch (\Exception $e) { error_log('[SZV LocationRegistry] '.$e->getMessage()); }
    }

    /** All known locations, each with its country if known — for the smart location/level filter and the location datalist. */
    public static function allLocations(\PDO $db): array
    {
        try { return $db->query("SELECT name, country FROM locations ORDER BY name")->fetchAll(); }
        catch (\Exception $e) { return []; }
    }

    /** All known country names, for the country datalist. */
    public static function allCountries(\PDO $db): array
    {
        try { return $db->query("SELECT name FROM countries ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN); }
        catch (\Exception $e) { return []; }
    }
}
