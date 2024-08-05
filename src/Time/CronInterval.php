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
use \Luminova\Exceptions\RuntimeException;
use \DateTimeZone;

class CronInterval 
{
    /**
     * Days of the week full and short names.
     *
     * @var array $dayNamesMap
     */
    private static array $dayNames = [
        'SUN' => 'Sunday',
        'MON' => 'Monday',
        'TUE' => 'Tuesday',
        'WED' => 'Wednesday',
        'RHU' => 'Thursday',
        'FRI' => 'Friday',
        'SAT' => 'Saturday'
    ];

    /**
     * Fixed intervals mapping.
     *
     * @var array $fixedIntervals
     */
    private static array $fixedIntervals = [
        '* * * * *' => 'PT1M',       // Every minute
        '0 * * * *' => 'PT1H',       // Every hour
        '0 0 * * 0' => 'P1W',        // Weekly on Sunday
        '0 0 0 0 1' => 'P1W',        // Weekly on Sunday
        '0 0 1 * *' => 'first day of this month',        // Monthly on the 1st day
        '0 0 1 1 *' => '1 january',        // Yearly on January 1st

    
        // Every N minutes
        '*/1 * * * *' => 'PT1M',     // Every 1 minute
        '*/2 * * * *' => 'PT2M',     // Every 2 minutes
        '*/3 * * * *' => 'PT3M',     // Every 3 minutes
        '*/5 * * * *' => 'PT5M',     // Every 5 minutes
        '*/10 * * * *' => 'PT10M',   // Every 10 minutes
        '*/15 * * * *' => 'PT15M',   // Every 15 minutes
        '*/30 * * * *' => 'PT30M',   // Every 30 minutes
    
        // Every N hours
        '0 */1 * * *' => 'PT1H',     // Every 1 hour
        '0 */2 * * *' => 'PT2H',     // Every 2 hours 
        '0 */3 * * *' => 'PT3H',     // Every 3 hours
        '0 */4 * * *' => 'PT4H',     // Every 4 hours
        '0 */6 * * *' => 'PT6H',     // Every 6 hours
        '0 */12 * * *' => 'PT12H',   // Every 12 hours 
    
        // Every N days
        '0 0 */3 * *' => 'P3D',      // Every 3 days
        '0 0 */5 * *' => 'P5D',      // Every 5 days
        '0 0 */7 * *' => 'P7D',      // Every 7 days
        '0 0 */10 * *' => 'P10D',    // Every 10 days
        '0 0 */15 * *' => 'P15D',    // Every 15 days
        '0 0 */30 * *' => 'P30D',    // Every 30 days

        // Week N
        '* * * * 0' => 'this Sunday', // Last day of week
    
        // Every N months
        '0 0 28-31 * *' => 'last day of this month',    // Last day of the month
        '0 0 1 */1 *' => 'P1M',      // Every 1 month
        '0 0 1 */2 *' => 'P2M',      // Every 2 months
        '0 0 1 */3 *' => 'P3M',      // Every 3 months
        '0 0 1 */4 *' => 'P4M',      // Every 4 months
        '0 0 1 */6 *' => 'P6M',      // Every 6 months
        '0 0 1 */12 *' => 'P12M',    // Every 12 months
    ];    

    /**
     * Converts a cron expression to a PHP interval string.
     *
     * @param string $expression The cron expression to convert.
     * @param DateTimeZone|string|null $timezone Optional. The timezone for date calculations. Default is null.
     * 
     * @return string|null The PHP interval string on success, null on error.
     * @throws RuntimeException If the cron expression is invalid.
     *
     * 
     * *    *    *    *    *
     * * -    -    -    -    -
     * |    |    |    |    |
     * |    |    |    |    +----- day of week (0 - 6) (Sunday=0)
     * |    |    |    +---------- month (1 - 12)
     * |    |    +--------------- day of month (1 - 31)
     * |    +-------------------- hour (0 - 23)
     * +------------------------- minute (0 - 59)
    */
    public static function convert(string $expression, DateTimeZone|string|null $timezone = null): array|string
    {
        if($expression === '' || strlen($expression) < 5){
            throw new RuntimeException(sprintf('Invalid cron timestamp expressions "%s".', $expression));
        }

        if(isset(self::$fixedIntervals[$expression])){
            return self::$fixedIntervals[$expression];
        }

        $parts = explode(' ', $expression);

        if (count($parts) !== 5) {
            throw new RuntimeException('Invalid cron expression format (expected 5 parts).');
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;
        $seconds = self::intervalInSeconds($minute, $hour, $day, $month, $weekday, $timezone);

        return 'PT' . $seconds . 'S';
    }

    /**
     * Calculates the interval in seconds based on the cron expression components.
     *
     * @param string $minute The minute component of the cron expression.
     * @param string $hour The hour component of the cron expression.
     * @param string $day The day component of the cron expression.
     * @param string $month The month component of the cron expression.
     * @param string $weekday The weekday component of the cron expression.
     * @param DateTimeZone|string|null $timezone Optional. The timezone for date calculations. Default is null.
     * 
     * @return int The interval in seconds.
     */
    private static function intervalInSeconds(
        string $minute, 
        string $hour, 
        string $day, 
        string $month, 
        string $weekday, 
        DateTimeZone|string|null $timezone = null
    ): int
    {
        $now = Time::now($timezone);
        $nextRun = clone $now;
    
        if ($minute === '*') {
            $nextRun = $nextRun->modify('+1 minute');
        } elseif (str_starts_with($minute, '*/')) {
            $interval = self::parseFormat($minute, 2);
            $nextRun = $nextRun->modify('+' . $interval . ' minutes');
        }
    
        if ($hour !== '*') {
            if (str_starts_with($hour, '*/')) {
                $interval = self::parseFormat($hour, 2);
                $nextRun = $nextRun->modify('+' . $interval . ' hours');
            } else {
                $interval = (int) $hour;
                $minute = (int) $minute;
                $nextRun = $nextRun->setTime($interval, $minute);
            }
        }
    
        if ($day !== '*' && str_starts_with($day, '*/')) {
            $interval = self::parseFormat($day, 2);
            $nextRun = $nextRun->modify('+' . $interval . ' days');
        }
    
        if ($month !== '*' && str_starts_with($month, '*/')) {
            $interval = self::parseFormat($month);
            $nextRun = $nextRun->modify('+' . $interval . ' months');
        }
    
        if ($weekday !== '*') {
            $nextRun = $nextRun->modify('next ' . self::getDayOfWeek($weekday));
        }
    
        return $nextRun->getTimestamp() - $now->getTimestamp();
    }
    
    /**
     * Determines the day of the week based on the provided weekday component.
     *
     * @param string $weekday The weekday component of the cron expression.
     * @param DateTime $dateTime The DateTime object representing the current date and time.
     * 
     * @return string The full name of the day of the week.
     * @throws RuntimeException If the weekday component is invalid.
     */
    private static function getDayOfWeek(string $weekday): string
    {
        if (is_numeric($weekday)) {
            return array_values(self::$dayNames)[$weekday % 7];
        } 
        
        $weekday = strtoupper($weekday);
        if (isset(self::$dayNames[$weekday])) {
            return self::$dayNames[$weekday];
        }
    
        throw new RuntimeException(sprintf('Invalid weekday "%s".', $weekday));
    }    

    /**
     * Parses the format of the cron expression component.
     *
     * @param string $component The component of the cron expression.
     * 
     * @return int The parsed value of the component.
     * @throws RuntimeException If the component format is invalid.
     */
    private static function parseFormat(string $component): int
    {
        if ($component === '*') {
            return 1; 
        }
        
        if (str_starts_with($component, '*/')) {
            return (int) substr($component, 2);
        } 

        $values = explode(',', $component);
        $validValues = [];
        foreach ($values as $value) {
            $value = (int) $value; 
            if ($value >= 0 && $value <= ($component === 'M' ? 12 : 59)) { 
                $validValues[] = $value;
            } else {
                throw new RuntimeException("Invalid value '$value' in component '$component'");
            }
        }

        return ($validValues === []) ? count($values) : max($validValues); 
    }
}