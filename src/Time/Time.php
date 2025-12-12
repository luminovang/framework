<?php
/**
 * Luminova Framework DateTime DateTimeImmutable
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Time;

use \DateTime;
use \Throwable;
use \Stringable;
use \DateInterval;
use \DateTimeZone;
use \DateTimeImmutable;
use \DateTimeInterface;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\DateTimeException;

class Time extends DateTimeImmutable implements Stringable
{
    /**
     * Timezone instance.
     *
     * @var DateTimeZone|null $timezone
     */
    private ?DateTimeZone $timezone = null;

    /**
     * Datetime format used when casting the instance to a string.
     *
     * @var string $stringFormat
     */
    private static string $stringFormat = 'Y-M-D H:i:s';

    /**
     * Standard datetime format used internally for construction and storage.
     *
     * @var string $defaultFormat
     */
    private static string $defaultFormat = 'Y-m-d H:i:s';

    /**
     * Regex that matches PHP relative-time keywords accepted by strtotime / DateTime::modify.
     *
     * @var string $relativePattern
     */
    private static string $relativePattern = '/this|next|last|tomorrow|yesterday|midnight|today|[+-]|first|last|ago/i';

    /**
     * Regex that matches the human-readable "N unit(s) ago" format produced by {@see ago()}.
     *
     * @var string $agoRelativePattern
     */
    private static string $agoRelativePattern = '/^\d+\s+(second|minute|hour|day|week|month|year|decade)s?\s+ago$/i';

    /**
     * Creates a new Time instance.
     *
     * Accepts any datetime string that PHP understands, including relative
     * expressions ("next Monday", "2 days ago") and absolute ISO strings.
     *
     * @param string|null $datetime Optional datetime string; defaults to now.
     * @param DateTimeZone|string|null $timezone Optional timezone; defaults to the PHP default.
     *
     * @throws DateTimeException If the underlying DateTimeImmutable construction fails.
     */
    public function __construct(?string $datetime = null, DateTimeZone|string|null $timezone = null)
    {
        $datetime ??= '';
        $timezone ??= date_default_timezone_get();
        $this->timezone = self::timezone($timezone);

        if ($datetime !== '' && !self::isAbsolute($datetime)) {
            $datetime = self::fromRelative($datetime, $this->timezone)
                ->format('Y-m-d H:i:s.u');
        }

        try {
            parent::__construct($datetime, $this->timezone);
        } catch (Throwable $e) {
            throw new DateTimeException(
                sprintf('Error occurred while constructing DateTimeImmutable object: %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Create a new instance representing the current date and time.
     *
     * @param DateTimeZone|string|null $timezone Optional timezone.
     *
     * @return static Return a new instance of time immutable object.
     * @throws DateTimeException If failed to create datetime object.
     */
    public static function now(DateTimeZone|string|null $timezone = null): self
    {
        return new self(timezone: $timezone);
    }

    /**
     * Create a new instance set to midnight (00:00:00) of the current day.
     *
     * @param DateTimeZone|string|null $timezone Optional timezone.
     *
     * @return static Return a new instance of time immutable object.
     * @throws DateTimeException If failed to create datetime object.
     */
    public static function today(DateTimeZone|string|null $timezone = null): self
    {
        return new self(date('Y-m-d 00:00:00'), $timezone);
    }

    /**
     * Create a new instance set to midnight (00:00:00) of yesterday.
     *
     * @param DateTimeZone|string|null $timezone Optional timezone.
     *
     * @return static Return a new instance of time immutable object.
     * @throws DateTimeException If failed to create datetime object.
     */
    public static function yesterday(DateTimeZone|string|null $timezone = null): self
    {
        return new self(date('Y-m-d 00:00:00', strtotime('-1 day')), $timezone);
    }

    /**
     * Create a new instance set to midnight (00:00:00) of tomorrow.
     *
     * @param DateTimeZone|string|null $timezone Optional timezone.
     *
     * @return static Return a new instance of time immutable object.
     * @throws DateTimeException If failed to create datetime object.
     */
    public static function tomorrow(DateTimeZone|string|null $timezone = null): self
    {
        return new self(date('Y-m-d 00:00:00', strtotime('+1 day')), $timezone);
    }

    /**
     * Parses an arbitrary datetime string and returns a new instance.
     *
     * Accepts absolute strings, relative keywords, and "N unit(s) ago" expressions.
     *
     * @param string $datetime Datetime string to parse (e.g. "first day of December 2020").
     * @param DateTimeZone|string|null $timezone Optional timezone.
     *
     * @return static Return a new instance of time immutable object.
     * @throws DateTimeException If failed to create datetime object.
     *
     * @example - Example:
     * ```php
     * $time = Time::parse('first day of December 2020');
     * ```
     */
    public static function parse(string $datetime, DateTimeZone|string|null $timezone = null): self
    {
        return new self($datetime, $timezone);
    }

    /**
     * Creates a new instance from individual date/time components.
     *
     * Any omitted component falls back to its current-date equivalent.
     *
     * @param int|null $year Optional year numeric value to drive from.
     * @param int|null $month Optional month to drive from.
     * @param int|null $day Optional day to drive from.
     * @param int|null $hour Optional hour to drive from.
     * @param int|null $minutes Optional minutes to drive from.
     * @param int|null $seconds Optional seconds to drive from.
     * @param DateTimeZone|string|null $timezone Optional timezone.
     *
     * @return static Return a new instance of time immutable object.
     * @throws DateTimeException If failed to create datetime object.
     */
    public static function createFrom(
        ?int $year    = null,
        ?int $month   = null,
        ?int $day     = null,
        ?int $hour    = null,
        ?int $minutes = null,
        ?int $seconds = null,
        DateTimeZone|string|null $timezone = null
    ): self 
    {
        $seconds ??= 0;
        $minutes ??= 0;
        $hour    ??= 0;
        $day     ??= (int) date('d');
        $month   ??= (int) date('m');
        $year    ??= (int) date('Y');

        return new self(
            date(self::$defaultFormat, strtotime("{$year}-{$month}-{$day} {$hour}:{$minutes}:{$seconds}")),
            $timezone
        );
    }

    /**
     * Creates a new instance from an existing DateTimeInterface, preserving its timezone.
     *
     * @param DateTimeInterface $datetime Source datetime object.
     *
     * @return static Return a new instance of time immutable object.
     * @throws DateTimeException If failed to create datetime object.
     */
    public static function fromInstance(DateTimeInterface $datetime): self
    {
        return new self(
            $datetime->format(self::$defaultFormat),
            $datetime->getTimezone() ?: null
        );
    }

    /**
     * Creates a new instance from a Unix timestamp.
     *
     * The timestamp is interpreted as UTC and then converted to the given timezone.
     *
     * @param int $timestamp The unix timestamp to drive from.
     * @param DateTimeZone|string|null $timezone  Target timezone; defaults to the PHP default.
     *
     * @return static Return a new instance of time immutable object.
     * @throws DateTimeException If failed to create datetime object.
     */
    public static function fromTimestamp(int $timestamp, DateTimeZone|string|null $timezone = null): self
    {
        $timezone ??= date_default_timezone_get();
        $time = new self(gmdate(self::$defaultFormat, $timestamp), 'UTC');

        return $time->setTimezone($timezone);
    }

    /**
     * Creates a new instance from date components only; the time is set to 00:00:00.
     *
     * Omitted components default to the corresponding values of the current date.
     *
     * @param int|null $year Optional year numeric value to drive from.
     * @param int|null $month Optional month numeric value to drive from.
     * @param int|null $day Optional days to drive from.
     * @param DateTimeZone|string|null $timezone Optional timezone.
     *
     * @return static Return a new instance of time immutable object.
     * @throws DateTimeException If failed to create datetime object.
     */
    public static function fromDate(
        ?int $year  = null,
        ?int $month = null,
        ?int $day   = null,
        DateTimeZone|string|null $timezone = null
    ): self 
    {
        return self::createFrom(year: $year, month: $month, day: $day, timezone: $timezone);
    }

    /**
     * Creates a new instance using today's date with the supplied time components.
     *
     * Omitted components default to 0.
     *
     * @param int|null $hour Optional hour of the day to drive from.
     * @param int|null $minutes Optional minutes to drive from.
     * @param int|null $seconds Optional seconds to drive from.
     * @param DateTimeZone|string|null $timezone Optional timezone.
     *
     * @return static Return a new instance of time immutable object.
     * @throws DateTimeException If failed to create datetime object.
     */
    public static function fromTime(
        ?int $hour = null,
        ?int $minutes = null,
        ?int $seconds = null,
        DateTimeZone|string|null $timezone = null
    ): self 
    {
        return self::createFrom(hour: $hour, minutes: $minutes, seconds: $seconds, timezone: $timezone);
    }

    /**
     * Resolves a relative datetime expression to an absolute object.
     *
     * Handles both "N unit(s) ago" strings and PHP relative keywords
     * (e.g. "next Monday", "-3 years").
     *
     * @param string $datetime Relative datetime string.
     * @param DateTimeZone|string|null $timezone Optional timezone applied to the resolved instant.
     *
     * @return static Return resolved datetime as Time object.
     * @throws DateTimeException If the expression cannot be resolved.
     * @example - Example:
     * ```php
     * $datetime = Time::fromRelative('10 hours ago')->format('Y-m-d H:i:s.u');
     * ```
     */
    public static function fromRelative(string $datetime, DateTimeZone|string|null $timezone = null): self
    {
        $timezone = ($timezone !== null) ? self::timezone($timezone) : $timezone;

        if (self::isTimeAgo($datetime)) {
            return self::fromTimeAgo($datetime, $timezone);
        }

        if (self::isRelative($datetime)) {
            $now = new self('now', $timezone);

            if ($now->modify($datetime) === false) {
                throw new DateTimeException("Invalid relative time '{$datetime}' passed");
            }
        } else {
            $now = new self($datetime, $timezone);
        }

        if ($timezone instanceof DateTimeZone) {
            $now = $now->setTimezone($timezone);
        }

        return $now;
    }

    /**
     * Creates a new DateTimeImmutable from a format string and a datetime string.
     *
     * Thin wrapper around {@see DateTimeImmutable::createFromFormat()}.
     *
     * @param string $format PHP date format string.
     * @param string $datetime Datetime string matching $format.
     * @param DateTimeZone|string|null $timezone Optional timezone.
     *
     * @return DateTimeImmutable|false New instance on success, false on failure.
     * @throws DateTimeException If failed to create datetime object.
     */
    public static function fromFormat(
        string $format,
        string $datetime,
        DateTimeZone|string|null $timezone = null
    ): DateTimeImmutable|bool 
    {
        try{
            return parent::createFromFormat($format, $datetime, $timezone);
        } catch(Throwable $e){
            throw new DateTimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create a new Time object from human-readable "N unit(s) ago" expression back into a datetime instance.
     *
     * Accepts "just now" as a special case. Supported units: second, minute, hour,
     * day, week, month, year, decade.
     *
     * @param string $expression Expression such as "3 hours ago" or "1 decade ago".
     * @param DateTimeZone|string|null $timezone Optional timezone for the resulting instance.
     *
     * @return static Return a new instance of time immutable object.
     * @throws DateTimeException If the format is invalid or the unit is unrecognized.
     */
    public static function fromTimeAgo(string $expression, DateTimeZone|string|null $timezone = null): self
    {
        $expression  = strtolower(trim($expression));
        $timezone = self::timezone($timezone ?? date_default_timezone_get());

        if ($expression === 'just now') {
            return (new self('now', $timezone))->setTimezone($timezone);
        }

        $parts = explode(' ', $expression, 3);
        if (count($parts) !== 3 || $parts[2] !== 'ago') {
            throw new DateTimeException('Invalid relative time format: "' . $expression . '"');
        }

        $quantity = (int) $parts[0];
        $unit     = rtrim($parts[1], 's');

        $intervals = [
            'second' => 'PT%dS',
            'minute' => 'PT%dM',
            'hour'   => 'PT%dH',
            'day'    => 'P%dD',
            'week'   => 'P%dW',
            'month'  => 'P%dM',
            'year'   => 'P%dY',
            'decade' => 'P%dY',
        ];

        if (!isset($intervals[$unit])) {
            throw new DateTimeException('Invalid time unit: "' . $unit . '"');
        }

        if ($unit === 'decade') {
            $quantity *= 10;
        }

        $datetime = new self('now', $timezone);

        return $datetime->sub(new DateInterval(sprintf($intervals[$unit], $quantity)));
    }

    /**
     * Normalizes a timezone value to a DateTimeZone instance.
     *
     * Returns the argument unchanged if it is already a DateTimeZone.
     *
     * @param DateTimeZone|string $timezone Timezone name or instance.
     *
     * @return DateTimeZone Return instance of timezone.
     * @throws \DateInvalidTimeZoneException If failed to create timezone object.
     */
    public static function timezone(DateTimeZone|string $timezone): DateTimeZone
    {
        return ($timezone instanceof DateTimeZone) 
            ? $timezone 
            : new DateTimeZone($timezone);
    }

    /**
     * Create current datetime formatted as "Y-m-d H:i:s".
     *
     * @param DateTimeZone|string|null $timezone Timezone to use; defaults to UTC.
     *
     * @return string Return formatted data string.
     * @throws DateTimeException If failed to create date.
     */
    public static function datetime(DateTimeZone|string|null $timezone = 'UTC'): string
    {
        return self::now($timezone)->format(self::$defaultFormat);
    }

    /**
     * Create a Time instance in UTC, converting from an optional source datetime.
     *
     * If no argument is supplied the current time is used.
     *
     * @param DateTimeInterface|string|null $datetime Source datetime object, string, or null for now.
     *
     * @return static Return a new instance normalized to UTC.
     * @throws DateTimeException If failed to create date.
     */
    public static function UTC(DateTimeInterface|string|null $datetime = null): self
    {
        $utc = new DateTimeZone('UTC');
        $datetime ??= 'now';

        if (is_string($datetime)) {
            $datetime = new self($datetime, $utc);
        }

        if ($datetime instanceof DateTimeImmutable) {
            return $datetime->setTimezone($utc);
        }

        return $datetime;
    }

    /**
     * Create social time ago expression from datetime.
     * 
     * This converts a datetime value to a human-readable relative string (e.g. "3 hours ago").
     *
     * @param DateTimeImmutable|string|int $datetime The source, a datetime object, an ISO string, or a Unix timestamp.
     * @param bool $full When true, returns all non-zero components (e.g. "1 hour, 3 minutes, 5 seconds ago").
     * @param DateTimeZone|string|null $timezone Optional timezone for the "now" reference point.
     *
     * @return string|false Relative string on success, false if $datetime could not be resolved.
     * @throws DateTimeException If failed to create date.
     */
    public static function ago(
        DateTimeImmutable|string|int $datetime,
        bool $full = false,
        DateTimeZone|string|null $timezone = null
    ): string|bool 
    {
        if (is_numeric($datetime)) {
            $datetime = self::fromTimestamp($datetime, $timezone);
        }

        if (is_string($datetime)) {
            $datetime = new static($datetime, $timezone);
        }

        if (!$datetime instanceof DateTimeImmutable) {
            return false;
        }

        return self::createTimeAgo($datetime, $full, $timezone);
    }

    /**
     * Checks whether at least $minutes have elapsed since the given datetime.
     *
     * @param DateTimeImmutable|string|int $datetime Reference point, datetime object, ISO string, or Unix timestamp.
     * @param int $minutes The number of minutes to test against.
     * @param DateTimeZone|string|null $timezone Timezone for the "now" reference; defaults to UTC.
     *
     * @return bool True if $minutes or more have passed, false otherwise.
     * @throws DateTimeException If the datetime cannot be resolved to a valid timestamp.
     */
    public static function passed(
        DateTimeImmutable|string|int $datetime,
        int $minutes,
        DateTimeZone|string|null $timezone = 'UTC'
    ): bool 
    {
        if (is_numeric($datetime)) {
            if ($datetime < $minutes) {
                return false;
            }

            $datetime = self::fromTimestamp((int) $datetime, $timezone);
        } elseif (!$datetime instanceof DateTimeImmutable) {
            $datetime = self::fromFormat(self::$defaultFormat, $datetime, $timezone);
        }

        $timestamp = $datetime->getTimestamp();

        if (!$timestamp) {
            throw new DateTimeException('Invalid datetime "' . $datetime . '" specified');
        }

        $interval = self::now($timezone)->diff($datetime);
        $difference = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;

        return $difference >= $minutes;
    }

    /**
     * Retrieve the ordinal suffix for a given day number (e.g. 1 → "1st", 22 → "22nd").
     *
     * @param int $day The day of the month.
     *
     * @return string Return day number with its ordinal suffix appended.
     */
    public static function suffix(int $day): string
    {
        $lastDigit  = $day % 10;
        $lastTwoDigits = $day % 100;

        if ($lastDigit === 1 && $lastTwoDigits !== 11) {
            return $day . 'st';
        }

        if ($lastDigit === 2 && $lastTwoDigits !== 12) {
            return $day . 'nd';
        }

        if ($lastDigit === 3 && $lastTwoDigits !== 13) {
            return $day . 'rd';
        }

        return $day . 'th';
    }

    /**
     * Retrieve a two-dimensional array of Unix timestamps representing a full calendar month.
     *
     * Each inner array is a week (Sunday–Saturday). The grid may include days from the
     * previous and next months to complete the first and last weeks.
     *
     * @param string|null $month Numeric month (1–12); defaults to the current month.
     * @param string|null $year Four-digit year; defaults to the current year.
     * @param DateTimeZone|string|null $timezone Optional timezone.
     *
     * @return array<int,array<int,int>> Array of weeks, each containing 7 Unix timestamps.
     */
    public static function calendar(
        ?string $month = null,
        ?string $year  = null,
        DateTimeZone|string|null $timezone = null
    ): array 
    {
        $month ??= date('n');
        $year  ??= date('Y');

        $firstDay = (new self("{$year}-{$month}-01", $timezone))->setTime(0, 0, 0);
        $lastDay  = (new self($firstDay->format('Y-m-t'), $timezone))->setTime(23, 59, 59);

        $start = $firstDay->modify('last Sunday')->getTimestamp();
        $stop  = $lastDay->modify('next Sunday')->getTimestamp();

        $calendar = [];
        while ($start < $stop) {
            $calendar[] = range($start, min($stop, $start + 6 * 86400), 86400);
            $start += 7 * 86400;
        }

        return $calendar;
    }

    /**
     * Retrieve an array of formatted date strings for every day in the given month.
     *
     * @param int|null $month Month number (1–12); defaults to the current month.
     * @param int|null $year Four-digit year; defaults to the current year.
     * @param string $format PHP date format for each entry; defaults to "d-M-Y".
     *
     * @return array<int,string> Return a list array of days.
     */
    public static function days(
        ?int $month = null,
        ?int $year = null,
        string $format = 'd-M-Y'
    ): array 
    {
        $month ??= date('n');
        $year  ??= date('Y');

        $maxDays = cal_days_in_month(CAL_GREGORIAN, (int) $month, (int) $year);
        $days = [];

        for ($day = 1; $day <= $maxDays; $day++) {
            $days[] = date($format, mktime(0, 0, 0, (int) $month, $day, (int) $year));
        }

        return $days;
    }

    /**
     * Retrieve an array mapping month numbers (1–12) to formatted month strings.
     *
     * @param string $format PHP date format for each entry; defaults to "M" (e.g. "Jan").
     * @param int|null $year Four-digit year, defaults to the current year.
     *
     * @return array<int,string> Return an associative array int-index, of months.
     */
    public static function months(string $format = 'M', ?int $year = null): array
    {
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = date($format, mktime(0, 0, 0, $month, 1, $year));
        }

        return $months;
    }

    /**
     * Retrieve the name of a month from its numeric value.
     *
     * @param int $month Month number (1 = January … 12 = December).
     * @param bool $fullname If true the full name (e.g. "January"), false for the abbreviation (e.g. "Jan").
     *
     * @return string Return the name of the month by its numeric value.
     */
    public static function month(int $month, bool $fullname = false): string
    {
        return self::createFromFormat('!m', (string) $month)
            ->format($fullname ? 'F' : 'M');
    }

    /**
     * Generates an inclusive list of years between a start and end year.
     *
     * Returns years in descending order when $start > $end, ascending when $start ≤ $end.
     *
     * @param int|string|null $start Starting year; defaults to the current year.
     * @param int|string|null $end Ending year, defaults to three years before $start.
     *
     * @return array<int,int> Return array list of years between `$starts` and `$end`.
     */
    public static function years(int|string|null $start = null, int|string|null $end = null): array
    {
        $start ??= date('Y');
        $end   ??= date('Y', strtotime('-3 years'));

        $end   = ($end === 0) ? (int) date('Y') : $end;
        $start = is_numeric($start) ? (int) $start : (int) date('Y');
        $end   = is_numeric($end) ? (int) $end : (int) date('Y');

        $years = [];

        if ($start <= $end) {
            for ($year = $start; $year <= $end; $year++) {
                $years[] = $year;
            }
        } else {
            for ($year = $start; $year >= $end; $year--) {
                $years[] = $year;
            }
        }

        return $years;
    }

    /**
     * Retrieve a list of 12-hour clock times at the given minute interval.
     *
     * The list always starts at 12:00AM and ends at 12:00AM of the next day (inclusive).
     *
     * @param int $interval Interval in minutes; defaults to 30.
     *
     * @return array<int,string> Return array list of times formatted as "g:iA" (e.g. "12:00AM", "12:30AM").
     */
    public static function hours(int $interval = 30): array
    {
        return array_map(
            static fn(int $seconds): string => date('g:iA', $seconds),
            range(0, 24 * 60 * 60, $interval * 60)
        );
    }

    /**
     * Determines if `$expression` contains a PHP relative-time keyword.
     *
     * Relative keywords include: this, next, last, tomorrow, yesterday, midnight,
     * today, +, -, first, ago.
     *
     * @param string $expression The relative time expression to check.
     *
     * @return bool Return true if expression matches relative time keyword. 
     */
    public static function isRelative(string $expression): bool
    {
        return preg_match(self::$relativePattern, $expression) === 1;
    }

    /**
     * Determines if `$expression` matches the human-readable "N unit(s) ago" format.
     *
     * @param string $expression The social time ago expression to check.
     *
     * @return bool Return true if expression matches relative time ago keyword. 
     */
    public static function isTimeAgo(string $expression): bool
    {
        return preg_match(self::$agoRelativePattern, $expression) === 1;
    }

    /**
     * Determines if `$expression` contains an absolute date component (YYYY-M-D).
     *
     * @param string $expression The time expression to check.
     *
     * @return bool Return true if expression matches absolute date format. 
     */
    public static function isAbsolute(string $expression): bool
    {
        return (bool) preg_match('/\d{4}-\d{1,2}-\d{1,2}/', $expression);
    }

    /**
     * Gets the four-digit year of the current instance (e.g. "2024").
     *
     * @return string Return the numeric value representing year.
     */
    public function getYear(): string
    {
        return (string) $this->toFormat('Y');
    }

    /**
     * Gets the numeric month of the current instance, with a leading zero (e.g. "04").
     *
     * @return string Return the numeric value representing month.
     */
    public function getMonth(): string
    {
        return (string) $this->toFormat('m');
    }

    /**
     * Gets the day of the month of the current instance, with a leading zero (e.g. "09").
     *
     * @return string Return the numeric value representing day.
     */
    public function getDay(): string
    {
        return (string) $this->toFormat('d');
    }

    /**
     * Gets the hour of the current instance in 24-hour format, with a leading zero (e.g. "14").
     *
     * @return string Return the numeric value representing hour.
     */
    public function getHour(): string
    {
        return (string) $this->toFormat('H');
    }

    /**
     * Gets the minutes of the current instance, with a leading zero (e.g. "05").
     *
     * @return string Return the numeric value representing minute.
     */
    public function getMinute(): string
    {
        return (string) $this->toFormat('i');
    }

    /**
     * Gets the seconds of the current instance, with a leading zero (e.g. "09").
     *
     * @return string Return the numeric value representing seconds.
     */
    public function getSecond(): string
    {
        return (string) $this->toFormat('s');
    }

    /**
     * Gets the zero-based day of the year (0 = January 1st, 365 = December 31st on a leap year).
     *
     * @return string Return the numeric value representing day of the year.
     */
    public function getDayOfYear(): string
    {
        return (string) $this->toFormat('z');
    }

    /**
     * Gets the ISO-8601 day of the week (1 = Monday … 7 = Sunday).
     *
     * @return string Return the numeric value representing day of week.
     */
    public function getDayOfWeek(): string
    {
        return (string) $this->toFormat('N');
    }

    /**
     * Gets the week of the month (1-based) for the current instance.
     *
     * Calculated as: ceil((dayOfMonth + dayOfWeekOfFirst - 1) / 7)
     * where dayOfWeekOfFirst is the ISO weekday (1=Mon…7=Sun) of the 1st of the month.
     *
     * @return string Return the numeric value representing week of the month.
     */
    public function getWeekOfMonth(): string
    {
        $dayOfMonth = (int) $this->format('j');
        $firstDayOfMonth = new self($this->format('Y-m-01'), $this->timezone);
        $firstWeekdayIndex = (int) $firstDayOfMonth->format('N'); 

        return (string) (int) ceil(($dayOfMonth + $firstWeekdayIndex - 1) / 7);
    }

    /**
     * Gets the ISO-8601 week number of the year (01–53).
     *
     * @return string Return the numeric value representing week of the year.
     */
    public function getWeekOfYear(): string
    {
        return (string) $this->toFormat('W');
    }

    /**
     * Gets the quarter of the year (1–4) for the current instance.
     *
     * @return string Return the numeric value representing quarter of the year.
     */
    public function getQuarter(): string
    {
        $quarter = (int) ceil((int) $this->format('n') / 3);

        return (string) $quarter;
    }

    /**
     * Gets the IANA name of the instance's timezone (e.g. "America/New_York").
     *
     * @return string|null Return the timezone name or null if couldn't be resolved.
     */
    public function getTimezoneName(): ?string
    {
        $tz = $this->timezone ?: $this->getTimezone();

        return ($tz instanceof DateTimeZone) 
            ? $tz->getName() 
            : null;
    }

    /**
     * Calculate the number of seconds remaining until the given target timestamp.
     *
     * @param int $timestamp The target Unix timestamp.
     *
     * @return int Seconds until $timestamp, or `0` if the target is in the past.
     */
    public function getMaxAge(int $timestamp): int
    {
        return max(0, $timestamp - $this->getTimestamp());
    }

    /**
     * Sets a new timezone to current time object. 
     * 
     * This will create a new instance with its timezone changed to the supplied value.
     *
     * @param DateTimeZone|string $timezone The target timezone.
     *
     * @return self Return new instance of class with updated timezone.
     * @throws DateTimeException If failed to create time object.
     * @throws \DateInvalidTimeZoneException If failed to create timezone object.
     */
    public function setTimezone(DateTimeZone|string $timezone): self
    {
        return self::fromInstance($this->toDatetime()
            ->setTimezone(self::timezone($timezone)));
    }

    /**
     * Converts the current instance to UTC.
     * 
     * This will create a new instance converted to UTC from previous Time object.
     *
     * @return self Return new instance with updated timezone to UTC.
     * @throws \DateInvalidTimeZoneException If failed to create timezone object.
     */
    public function toUTC(): self
    {
        return $this->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Converts the current instance to relative social time ago expression.
     * 
     * This transforms the Time object to a human-readable relative expression (e.g. "3 hours ago").
     *
     * @param bool $full When true, all non-zero time components are included.
     * @param DateTimeZone|string|null $timezone Optional timezone for the "now" reference point.
     *
     * @return string Return social time ago format of current time object.
     */
    public function toTimeAgo(bool $full = false, DateTimeZone|string|null $timezone = null): string
    {
        return self::createTimeAgo($this, $full, $timezone);
    }

    /**
     * Returns the instance formatted according to a PHP date format string.
     *
     * Falls back to default format `Y-M-D H:i:s`, when no format is supplied.
     *
     * @param string|null $format PHP date format (e.g. "Y-m-d H:i:s"); null uses the default display format.
     *
     * @return string|false Formatted string, or false if formatting fails.
     */
    public function toFormat(?string $format = null): string|bool
    {
        $format ??= self::$stringFormat;
        return $this->format($format);
    }

    /**
     * Convert the time component of the current instance formatted as "H:i:s" (e.g. "17:05:09").
     *
     * @return string|null Return time format from current object.
     */
    public function toTime(): ?string
    {
        return $this->toFormat('H:i:s') ?: null;
    }

    /**
     * Converts the Time object to a mutable DateTime.
     * 
     * This create copy of the current instance, preserving 
     * the timestamp, timezone and return instance of `DateTime`.
     *
     * @return DateTime Return new instance of DateTime object.
     * @throws DateTimeException If failed to create datetime object.
     */
    public function toDatetime(): DateTime
    {
        return (new DateTime('', $this->timezone ?? $this->getTimezone()))
            ->setTimestamp($this->getTimestamp());
    }

    /**
     * Returns the current time formatted with {@see $stringFormat}.
     *
     * @return string Return formatted datetime string.
     */
    public function toString(): string
    {
        return (string) $this->format(self::$stringFormat);
    }

    /**
     * Converts datetime to Unix timestamp format.
     *
     * @param DateTimeInterface|string|int $datetime The datetime to convert to Unix timestamp.
     * 
     * @return int Returns a Unix timestamp.
     * @throws DateTimeException If invalid datetime format.
     */
    public static function toTimestamp(DateTimeInterface|string|int $datetime): int
    {
        if ($datetime instanceof DateTimeInterface) {
            $datetime = (int) $datetime->format('U');
        } elseif (!is_numeric($datetime)) {
            $formatted = strtotime($datetime);

            if ($formatted === false) {
                throw new DateTimeException(sprintf(
                    'Failed to convert invalid time format: %s', 
                    $datetime
                ));
            }

            $datetime = $formatted;
        }

        if (!is_string($datetime) && !is_numeric($datetime)) {
            throw new DateTimeException(sprintf('Invalid time format: %s', gettype($datetime)));
        }
      
        return ($datetime > 0) ? (int) $datetime : 0;
    }

    /**
     * Convert datetime or DateInterval to seconds.
     * 
     * @param DateInterval|DateTimeInterface|int|null $datetime The datetime or internal object to convert
     * 
     * @return int Return converted date interval in seconds.
     */
    public static function toSeconds(
        DateInterval|DateTimeInterface|int|null $datetime,
        DateTimeZone|string|null $timezone = null
    ): int
    {
        if($datetime === null){
            return 0;
        }

        if(is_numeric($datetime)){
            return $datetime;
        }

        $timezone = ($timezone === null) ? null : self::timezone($timezone);
        $now = new DateTime(timezone: $timezone);
        
        if($datetime instanceof DateInterval){
            return $now->add($datetime)->getTimestamp() - $now->getTimestamp();
        }

        if($datetime instanceof DateTimeInterface){
            $diff = $now->diff($datetime);
     
            return $diff->s 
                + ($diff->i * 60) 
                + ($diff->h * 3600) 
                + ($diff->d * 86400) 
                + ($diff->m * 2_592_000) 
                + ($diff->y * 31_536_000);
        }

        return 0;
    }

    /**
     * Get a new Time instance advanced by the given interval cycle.
     *
     * Adds a predefined interval to the current instance using the given cycle
     * and optional multiplier. Supported cycles include hourly, daily, weekly,
     * monthly, quarterly, yearly, biannual, and decade intervals.
     *
     * This method does not modify the current instance. A new Time instance is returned.
     *
     * @param string $cycle The interval cycle to apply. Supported values:
     *                      `hourly`, `daily`, `weekly`, `monthly`, `quarterly`,
     *                      `yearly`, `annually`, `decade`.
     * @param int $unit Number of cycle units to add (minimum: 1).
     *
     * @return static Return a new Time instance with the interval added.
     *
     * @example - Add one week.
     * ```php
     * $billing = Time::parse('2026-01-01 10:00:00');
     *
     * $next = $billing->toNextCycle('weekly');
     * // Returns: 2026-01-08 10:00:00
     * ```
     *
     * @example - Add two quarters.
     * ```php
     * $billing->toNextCycle('quarterly', 2);
     * // Adds 6 months
     * ```
     *
     * @example - Add three years.
     * ```php
     * $billing->toNextCycle('yearly', 3);
     * ```
     */
    public function toNextCycle(string $cycle = 'monthly', int $unit = 1): self
    {
        $unit = max(1, $unit);
        $duration = match (strtolower(trim($cycle))) {
            'hourly'    => "PT{$unit}H",
            'daily'     => "P{$unit}D",
            'weekly'    => "P{$unit}W",
            'monthly'   => "P{$unit}M",
            'yearly',   => "P{$unit}Y",

            'quarterly' => 'P' . ($unit * 3) . 'M',
            'biannual'  => 'P' . ($unit * 6) . 'M',
            'annually'  => "P{$unit}Y",
            'decade'    => 'P' . ($unit * 10) . 'Y',
            default     => "P{$unit}M"
        };

        return $this->add(new DateInterval($duration));
    }

    /**
     * Returns true when the current instant falls within a daylight saving time period.
     *
     * @return bool Return true if is daylight saving, otherwise false.
     */
    public function isDaylight(): bool
    {
        return $this->format('I') === '1';
    }

    /**
     * Returns true when the instance's timezone matches the PHP runtime default timezone.
     *
     * @return bool Return true if current Time object (timezone), is same as default system timezone.
     */
    public function isSystemTimezone(): bool
    {
        return date_default_timezone_get() === $this->getTimezoneName();
    }

    /**
     * Returns true when the instance's timezone offset is exactly 0 (UTC / GMT).
     *
     * @return bool Return true if timezone offset is UTC.
     */
    public function isUtc(): bool
    {
        return $this->getOffset() === 0;
    }

    /**
     * Returns the current time formatted with {@see self::toString()}.
     *
     * @return string Return datetime string format.
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Proxies property-style access to any public getX() method.
     *
     * Example: $time->year calls $time->getYear().
     *
     * @param string $name Property name (matched case-insensitively to getX methods).
     *
     * @return DateTimeInterface|DateTimeZone|array|string|bool|int|null
     */
    public function __get(string $name): mixed
    {
        $method = 'get' . ucfirst($name);

        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        return null;
    }

    /**
     * Static method proxy.
     *
     * @param string $method The target method name.
     * @param array $arguments Optional method arguments.
     * 
     * @return mixed Return result of called method.
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (method_exists($this, $method)) {
            return $this->{$method}(...$arguments);
        }

        throw new RuntimeException(sprintf(
            'Method: %s does not exist in %s',
            $method,
            static::class
        ));
    }

    /**
     * Restores the instance after unserialization.
     *
     * Re-initialize the timezone property and calls the parent constructor.
     * 
     * @return void
     */
    public function __wakeup(): void
    {
        $this->timezone = new DateTimeZone(
            $this->getTimezoneName() ?: 'UTC'
        );

        parent::__construct('now', $this->timezone);
    }

    /**
     * Builds a human-readable relative time string for a given datetime.
     *
     * @param DateTimeImmutable $datetime Reference datetime (in the past).
     * @param bool $full Include all non-zero units when true.
     * @param DateTimeZone|string|null $timezone Timezone for the "now" anchor.
     *
     * @return string Return E.g. "3 hours ago", "1 decade, 2 years ago", or "just now".
     */
    private static function createTimeAgo(
        DateTimeImmutable $datetime,
        bool $full = false,
        DateTimeZone|string|null $timezone = null
    ): string 
    {
        $now = self::now($timezone);
        $elapsed = $now->diff($datetime);

        $weeks = (int) floor($elapsed->d / 7);
        $elapsed->d -= $weeks * 7;

        $decades  = (int) floor($elapsed->y / 10);
        $elapsed->y -= $decades * 10;

        $units = [
            ['decade', $decades],
            ['year',   $elapsed->y],
            ['month',  $elapsed->m],
            ['week',   $weeks],
            ['day',    $elapsed->d],
            ['hour',   $elapsed->h],
            ['minute', $elapsed->i],
            ['second', $elapsed->s],
        ];

        $parts = [];
        foreach ($units as [$label, $value]) {
            if ($value <= 0) {
                continue;
            }

            $suffix = ($value > 1) ? 's' : '';
            $part = "{$value} {$label}{$suffix}";

            if (!$full) {
                return $part . ' ago';
            }

            $parts[] = $part;
        }

        if($part === []){
            return 'just now';
        }

        return implode(', ', $parts) . ' ago';
    }

    /**
     * @deprecated Use {@see isTimeAgo()} instead.
     */
    public static function isAgo(string $datetime): bool
    {
        return self::isTimeAgo($datetime);
    }

    /**
     * @deprecated Use {@see fromTimeAgo()} instead.
     *
     * @param string $ago
     * @param DateTimeZone|string|null $timezone
     *
     * @return DateTimeInterface
     */
    public static function agoToDatetime(
        string $ago,
        DateTimeZone|string|null $timezone = null
    ): DateTimeInterface
    {
        return self::fromTimeAgo($ago, $timezone);
    }
}