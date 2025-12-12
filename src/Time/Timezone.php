<?php
declare(strict_types=1);
/**
 * Luminova Framework DateTimezone
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Time;

use \Throwable;
use \DateTimeZone;
use \DateInvalidTimeZoneException;

final class Timezone
{
    /**
     * UTC timezone.
     * 
     * @var string UTC_TIMEZONE
     */
    public const UTC_TIMEZONE = 'UTC';

    /**
     * GMT timezone.
     * 
     * @var string GMT_TIMEZONE
     */
    public const GMT_TIMEZONE = 'GMT';

    /**
     * Timezone lookups.
     *
     * @var array $timezones
     */
    private static array $timezones = [];

    /**
     * Create a timezone object from string.
     *
     * Returns the argument unchanged if it is already a DateTimeZone.
     *
     * @param DateTimeZone|string $timezone Timezone name or instance.
     *
     * @return DateTimeZone Return instance of timezone.
     * @throws DateInvalidTimeZoneException If failed to create timezone object.
     */
    public static function from(DateTimeZone|string $timezone): DateTimeZone
    {
        if($timezone instanceof DateTimeZone){
            return $timezone;
        }

        return new DateTimeZone(trim($timezone));
    }

    /**
     * Compare 2 timezone are equals.
     *
     * @param DateTimeZone|string $timezone
     * @param DateTimeZone|string $needle
     * 
     * @return bool Return true if they are both same, otherwise false.
     */
    public static function isEquals(DateTimeZone|string $timezone, DateTimeZone|string $needle): bool
    {
        if($timezone && $needle && $timezone === $needle){
            return true;
        }

        try{
            return self::from($timezone)->getName() === self::from($needle)->getName();
        } catch(Throwable){
            return false;
        }
    }

    /**
     * hecks whether the given value is a valid timezone.
     *
     * Accepts both IANA timezone identifiers and fixed UTC offsets.
     *
     * @param string $timezone Timezone identifier string.
     *
     * @return bool True if timezone exists, false otherwise.
     */
    public static function isTimezone(string $timezone): bool 
    {
        if(trim($timezone) === ''){
            return false;
        }

        if(self::isTimezoneIdentifier($timezone)){
            return true;
        }

        try{
            return self::from($timezone)->getName() !== '';
        } catch(Throwable){
            return false;
        }
    }

    /**
     * Returns whether the instance uses the current PHP runtime default timezone.
     * 
     * @param DateTimeZone|string $timezone Timezone name or instance.
     *
     * @return bool True if the instance's timezone matches the PHP default timezone.
     */
    public static function isSystemTimezone(DateTimeZone|string $timezone): bool
    {
        return self::isEquals(
            $timezone,
            date_default_timezone_get()
        );
    }

    /**
     * Returns whether the instance uses the application's configured timezone.
     *
     * @param DateTimeZone|string $timezone Timezone name or instance.
     * 
     * @return bool True if the instance's timezone matches the configured application timezone.
     */
    public static function isAppTimezone(DateTimeZone|string $timezone): bool
    {
        return self::isEquals(
            $timezone,
            env('app.timezone', self::UTC_TIMEZONE)
        );
    }

    /**
     * hecks whether the given value is a valid IANA timezone identifier.
     *
     * Results can be filtered by timezone group and optional country code.
     *
     * @param string $timezone Timezone identifier string.
     * @param int $tzGroup One of DateTimeZone::* constants (e.g. DateTimeZone::ALL).
     * @param string|null $tzCountryCode Optional country code filter (e.g. "MY", "US").
     *
     * @return bool True if timezone exists, false otherwise.
     */
    public static function isTimezoneIdentifier(
        string $timezone,
        int $tzGroup = DateTimeZone::ALL,
        ?string $tzCountryCode = null
    ): bool 
    {
        if ($timezone === '') {
            return false;
        }

        if ($timezone === self::UTC_TIMEZONE || $timezone === self::GMT_TIMEZONE) {
            return true;
        }

        if (!preg_match('/^[A-Za-z_]+(?:\/[A-Za-z0-9_+.-]+)+$/', $timezone)) {
            return false;
        }

        $key = self::fromCacheReference(
            true, 
            $tzGroup, 
            $tzCountryCode
        );

        return isset(self::$timezones[$key][$timezone]);
    }

    /**
     * Retrieve list of timezone identifiers.
     *
     * Returns PHP timezone identifiers filtered by group and optional country code.
     * Results are cached in-memory per combination for performance.
     *
     * @param int $tzGroup One of DateTimeZone::* constants (e.g. DateTimeZone::ALL).
     * @param string|null $tzCountryCode Optional country code filter (ISO 3166-1 alpha-2).
     *
     * @return array List of timezone identifiers.
     */
    public static function getTimezones(int $tzGroup = DateTimeZone::ALL, ?string $tzCountryCode = null ): array 
    {
        $key = self::fromCacheReference(
            false, 
            $tzGroup, 
            $tzCountryCode
        );

        return self::$timezones[$key] ?? [];
    }

    /**
     * Get a cache reference key for timezone data.
     *
     * This method does not return timezone data directly. Instead, it returns
     * a cache key that points to either:
     * - A timezone identifier list
     * - A flipped lookup map (for fast existence checks)
     *
     * The referenced data is lazily initialized and stored in-memory to avoid
     * repeated calls to timezone listIdentifiers and array_flip().
     *
     * Cache structure:
     * - `tz:{group}:{country}` → timezone list
     * - `fl:tz:{group}:{country}` → flipped lookup map
     *
     * @param bool $flip Whether to return a lookup-map reference instead of list reference.
     * @param int $tzGroup Timezone group filter (DateTimeZone::* constants).
     * @param string|null $tzCountryCode Optional ISO country code filter.
     *
     * @return string Cache reference key pointing to stored timezone data.
     */
    private static function fromCacheReference(
        bool $flip = false,
        int $tzGroup = DateTimeZone::ALL,
        ?string $tzCountryCode = null
    ): string 
    {
        $key = "tz:{$tzGroup}:{$tzCountryCode}";

        if (!isset(self::$timezones[$key])) {
            self::$timezones[$key] = DateTimeZone::listIdentifiers(
                $tzGroup,
                $tzCountryCode
            );
        }

        if (!$flip) {
            return $key;
        }

        $fk = "fl:{$key}";

        self::$timezones[$fk] ??= array_flip(self::$timezones[$key]);

        return $fk;
    }
}