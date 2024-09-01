<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Time;

use \Luminova\Time\Time;
use \DateTimeZone;
use \DateInterval;
use \Luminova\Exceptions\InvalidArgumentException;

class Task
{
    /**
     * Check if the current time is between opening and closing times.
     * 
     * @param string $openDatetime Opening date and time (e.g., '2023-09-25 08:00AM').
     * @param string $closeDatetime Closing date and time (e.g., '2023-09-25 05:00PM').
     * @param DateTimeZone|string|null $timezone Optional timezone string.
     *
     * @return bool Returns true if the task is still open, false otherwise.
     * 
     * > This utility function is useful for checking business opening and closing hours.
     */
    public static function isOpen(string $openDatetime, string $closeDatetime, DateTimeZone|string|null $timezone = 'UTC'): bool
    {
        $opening = self::format($openDatetime, $timezone);
        $closing = self::format($closeDatetime, $timezone);
        $nowTime = Time::now($timezone);

        if ($closing <= $opening) {
            $closing->add(new DateInterval('P1D'));
        }

        return ($nowTime > $opening && $nowTime >= $closing);
    }

    /**
     * Check if a given date and time has passed.
     *
     * @param string $datetime The expiration date and time (e.g., '2023-09-25 08:00AM').
     * @param DateTimeZone|string|null $timezone Optional timezone string.
     *
     * @return bool Returns true if the task has expired, false otherwise.
     * 
     * > Useful for checking if a deal or promo code has expired.
     */
    public static function expired(string $datetime, DateTimeZone|string|null $timezone = 'UTC'): bool
    {
        $now = Time::now($timezone);
        $expiration = Time::parse($datetime, $timezone);

        return $now > $expiration;
    }

    /**
     * Check if a given datetime string has reached or passed. 
     *
     * @param string $datetime The starting date and time (e.g., '2023-09-25 08:00AM').
     * @param DateTimeZone|string|null $timezone Optional timezone string.
     *
     * @return bool Returns true if the date has passed, expired or is the current day, false otherwise if still in future.
     * 
     * > Useful for checking if a deal or promo code has started.
     */
    public static function started(string $datetime, DateTimeZone|string|null $timezone = 'UTC'): bool
    {
        $now = Time::now($timezone);
        $starting = Time::parse($datetime, $timezone);
        
        return $now >= $starting;
    }

    /**
     * Check if the given date is within a specified number of days before expiration.
     *
     * @param string $datetime Expiration date and time (e.g., '2023-09-25 08:00AM').
     * @param int $days Number of days to check before expiration (default: 2). 
     * @param DateTimeZone|string|null $timezone Optional timezone string.
     *
     * @return bool Returns true if it's within the specified number of days before expiration.
     * @throws InvalidArgumentException If invalid days was passed.
     * 
     * > This method is useful to check and send notification days before subscription expiration.
     */
    public static function before(string $datetime, int $days = 2, DateTimeZone|string|null $timezone = 'UTC'): bool
    {
        if($days < 1){
            throw new InvalidArgumentException('Days must be greater than 0 and non-negative integer.');
        }

        $nowTime = Time::now($timezone);
        $expires = self::format($datetime,  $timezone);
        $threshold = $expires->modify("-{$days} days");

        return ($nowTime >= $threshold);
    }

    /**
     * Format datetime to 'Y-m-d H:iA'.
     * 
     * @param string $datetime Date and time to format.
     * @param DateTimeZone|string|null $timezone Optional timezone string.
     * 
     * @return Time Returns a new Time instance.
    */
    private static function format(string $datetime, DateTimeZone|string|null $timezone = 'UTC'): Time
    {
        return Time::fromFormat('Y-m-d H:iA', $datetime, $timezone);
    }
}