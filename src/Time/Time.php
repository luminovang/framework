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

use \DateTimeImmutable;
use \DateTimeZone;
use \DateTime;
use \IntlDateFormatter;
use \DateTimeInterface;
use \DateInterval;
use \Exception;
use \Luminova\Exceptions\DatetimeException;

class Time extends DateTimeImmutable
{
    /**
     * Timezone instance.
     * 
     * @var DateTimeZone
    */
    private ?DateTimeZone $timezone = null;

    /**
     * Default datetime format to use when displaying datetime string.
     *
     * @var string $stringFormat
    */
    private static string $stringFormat = 'Y-M-d H:m:s';

    /**
     * Default datetime format to use.
     *
     * @var string $defaultFormat
    */
    private static string $defaultFormat = 'Y-m-d H:i:s';

    /**
     * Regular expression pattern for relative time keywords.
     * 
     * @var string $relativePattern 
    */
    private static string $relativePattern = '/this|next|last|tomorrow|yesterday|midnight|today|[+-]|first|last|ago/i';

    /**
     * Regular expression pattern for relative time ago keywords.
     * 
     * @var string $agoRelativePattern 
    */
    private static string $agoRelativePattern = '/^\d+\s+(second|minute|hour|day|week|month|year)s?\s+ago$/i';

    /**
     * Time constructor.
     *
     * @param string|null $datetime Optional datetime string.
     * @param DateTimeZone|string|null $timezone Optional timezone.
     *
     * @throws DatetimeException Throws if error occurs during DateTimeImmutable object construction.
    */
    public function __construct(?string $datetime = null, DateTimeZone|string|null $timezone = null)
    {
        $datetime ??= '';
        $timezone ??= date_default_timezone_get();
        $this->timezone = static::timezone($timezone);
            
        if ($datetime !== '' && !static::isAbsolute($datetime)) {
            $datetime = static::fromRelative($datetime);
        }

        try {
            parent::__construct($datetime, $this->timezone);
        } catch (Exception $e) {
            throw new DatetimeException('Error occurred while constructing DateTimeImmutable object: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Converts string to timezone instance if not already a timezone instance.
     *
     * @param DateTimeZone|string $timezone Optional timezone to associate with the current DateTime instance.
     *
     * @return DateTimeZone The timezone instance.
    */
    public static function timezone(DateTimeZone|string $timezone): DateTimeZone
    {
        return ($timezone instanceof DateTimeZone) ? $timezone : new DateTimeZone($timezone);
    }

    /**
     * Returns current DateTime instance with the timezone set.
     *
     * @param DateTimeZone|string|null $timezone Optional timezone.
     *
     * @return self New DateTimeImmutable object.
     * @throws DatetimeException Throws if any error occurs.
     */
    public static function now(DateTimeZone|string|null $timezone = null): self
    {
        return new self(null, $timezone);
    }

    /**
     * Returns a new Time instance while parsing a datetime string.
     *
     * @param string $datetime Datetime string to parse.
     * @param DateTimeZone|string|null $timezone Optional timezone.
     *
     * @return self New DateTimeImmutable object.
     * @throws DatetimeException Throws if any error occurs.
     *
     * @example `$time = Time::parse('first day of December 2020');`
     */
    public static function parse(string $datetime, DateTimeZone|string|null $timezone = null): self
    {
        return new self($datetime, $timezone);
    }

    /**
     * Return a new time with the time set to midnight.
     *
     * @param DateTimeZone|string|null $timezone
     * @param ?string $local 
     *
     * @return self Return new DateTimeImmutable object.
     *
     * @throws DatetimeException Throws if any error occurred.
     */
    public static function today(DateTimeZone|string|null $timezone = null): self
    {
        return new self(date('Y-m-d 00:00:00'), $timezone);
    }

    /**
     * Returns an instance set to midnight yesterday morning.
     *
     * @param DateTimeZone|string|null $timezone
     * @param ?string $local 
     *
     * @return self Return new DateTimeImmutable object.
     *
     * @throws DatetimeException Throws if any error occurred.
     */
    public static function yesterday(DateTimeZone|string|null $timezone = null): self
    {
        return new self(date('Y-m-d 00:00:00', strtotime('-1 day')), $timezone);
    }

    /**
     * Set the TimeZone associated with the DateTime, and returns a new instance with the updated timezone.
     *
     * @param DateTimeZone|string $timezone Timezone to set.
     *
     * @return self Return new DateTimeImmutable object.
     * @throws DatetimeException Throws if any error occurred.
    */
    public function setTimezone(DateTimeZone|string $timezone): self
    {
        $timezone = static::timezone($timezone);

        return self::fromInstance($this->toDatetime()->setTimezone($timezone));
    }

    /**
     * Gets year from the current datetime format.
     *
     * @return string Return year
    */
    public function getYear(): string
    {
        return $this->toFormat('y');
    }

    /**
     * Gets month from the current datetime format.
     *
     * @return string Return month.
     */
    public function getMonth(): string
    {
        return $this->toFormat('M');
    }

    /**
     * Gets day from the current datetime format.
     *
     * @return string Return day of the month
     */
    public function getDay(): string
    {
        return $this->toFormat('d');
    }

    /**
     * Gets hour (in 24-hour format), from the current datetime format.
     *
     * @return string Return hours of the day.
     */
    public function getHour(): string
    {
        return $this->toFormat('H');
    }

    /**
     * Gets minutes from the current datetime format.
     *
     * @return string Return minutes.
     */
    public function getMinute(): string
    {
        return $this->toFormat('m');
    }

    /**
     * Gets seconds from the current datetime format.
     *
     * @return string Return seconds.
     */
    public function getSecond(): string
    {
        return $this->toFormat('s');
    }


    /**
     * Gets day of the year, from the current datetime format.
     *
     * @return string Return day of the year.
     */
    public function getDayOfYear(): string
    {
        return $this->toFormat('D');
    }

    /**
     * Gets week of the month, from the current datetime format.
     *
     * @return string Return week of the month.
     */
    public function getWeekOfMonth(): string
    {
        return $this->toFormat('W');
    }

    /**
     * Gets week of the year from the current datetime format.
     *
     * @return string Return week of the year.
     */
    public function getWeekOfYear(): string
    {
        return $this->toFormat('w');
    }

     /**
     * Gets week of the day from the current datetime format.
     *
     * @return string Return day of the week.
     */
    public function getDayOfWeek(): string
    {
        return $this->toFormat('c');
    }

    /**
     * Gets quarter of the year, from the current datetime format.
     *
     * @return string Return quarter of the year.
     */
    public function getQuarter(): string
    {
        return $this->toFormat('Q');
    }

    /**
     * Return string datetime of this format 'yyyy-mm-dd H:i:s'.
     * @param null|DateTimeZone|string $timezone Optional timezone string.
     * 
     * @return string Returns datetime string.
    */
    public static function datetime(DateTimeZone|string|null $timezone = 'UTC'): string
    {
        return static::now($timezone)->format(static::$defaultFormat);
    }

    /**
     * Determine if the current time is already in daylight savings.
     * 
     * @return bool Return true if the current time is already in daylight saving, false otherwise.
    */
    public function isDaylight(): bool
    {
        return $this->format('I') === '1'; 
    }

    /**
     * Check whether the passed timezone is the same as the local timezone.
     * 
     * @return bool true if the passed timezone is the same as the local timezone false otherswise.
    */
    public function isLocalTimezone(): bool
    {
        $local = date_default_timezone_get();

        return $local === $this->timezone->getName();
    }

    /**
     * Returns boolean whether object is in UTC.
     * 
     * @return bool Whether the timezone offset is UTC.
    */
    public function isUtc(): bool
    {
        return $this->getOffset() === 0;
    }

    /**
     * Returns the name of the current timezone.
     * 
     * @return string The name of the current timezone.
    */
    public function getTimezoneName(): string
    {
        return $this->timezone->getName();
    }

     /**
     * Returns Datetime instance of UTC timezone.
     *
     * @param DateTimeInterface|Time|string $datetime Datetime object or string
     *
     * @return DateTime Return new datetime instance of UTC timezone.
     * @throws DatetimeException Throws if any error occurred.
     */
    public function getInstanceUtc(DateTimeInterface|Time|string $datetime, ?string $timezone = null):  DateTime
    {
        if ($datetime instanceof Time) {
            $datetime = $datetime->toDatetime();
        } elseif (is_string($datetime)) {
            $timezone ??= $this->timezone;
            $datetime = new DateTime($datetime, static::timezone($timezone));
        }

        if ($datetime instanceof DateTime || $datetime instanceof DateTimeImmutable) {
            $datetime = $datetime->setTimezone(new DateTimeZone('UTC'));
        }

        return $datetime;
    }

    /**
     * Returns a formatted datetime to your prefered format.
     * 
     * @param null|string $format Formt to return (default: `yyyy-MM-dd HH:mm:ss`).
     * 
     * @return false|string Formatted datetime string otherwise false.
    */
    public function toFormat(?string $format = null): false|string
    {
        $format ??= $this->stringFormat;

        return IntlDateFormatter::formatObject($this->toDatetime(), $format);
    }

    /**
     * Returns a formatted time string (ex. 17:17:17).
     *
     * @return string Formatted time string otherwise false.
    */
    public function toTime(): false|string
    {
        return $this->toFormat('HH:mm:ss');
    }

    /**
     * Converts the current instance to a mutable DateTime object.
     * 
     * @return DateTime Return new datetime object.
     * @throws DatetimeException Throws if any error occurred.
     */
    public function toDatetime(): DateTime
    {
        $dateTime = new DateTime('', $this->getTimezone());
        $dateTime->setTimestamp(parent::getTimestamp());

        return $dateTime;
    }

    /**
     * Takes an instance of DateTimeInterface and returns an instance of Time with it's same values.
     *
     * @param DateTimeInterface $datetime An instance of DateTimeInterface.
     * 
     * @return self Return new DateTimeImmutable object.
     * @throws DatetimeException Throws if any error occurred.
     */
    public static function fromInstance(DateTimeInterface $datetime): self
    {
        $date = $datetime->format(self::$defaultFormat);
        $timezone = $datetime->getTimezone();

        return new self($date, $timezone);
    }

    /**
     * Returns a new instance with the datetime set based on the provided UNIX timestamp.
     *
     * @param int $timestamp Timestamp to create datetime from.
     * @param DateTimeZone|string|null $timezone Optional timezone to associate with current DateTime instance.
     *
     * @return self Return new DateTimeImmutable object.
     *
     * @throws DatetimeException Throws if any error occurred.
     */
    public static function fromTimestamp(int $timestamp, DateTimeZone|string|null $timezone = null): self
    {
        $time = new self(gmdate(self::$defaultFormat, $timestamp), 'UTC');

        $timezone ??= date_default_timezone_get();

        return $time->setTimezone($timezone);
    }

     /**
     * Returns a new instance based on the year, month and day. If any of those three are left empty, will default to the current value.
     *
     * @param int|null $year Year to pass.
     * @param int|null $month Month to pass.
     * @param int|null $day Day to pass.
     * @param DateTimeZone|string|null $timezone Optional timezone to associate with current DateTime instance.
     *
     * @return self Return new DateTimeImmutable object.
     *
     * @throws DatetimeException Throws if any error occurred.
     */
    public static function fromDate(
        ?int $year = null, 
        ?int $month = null, 
        ?int $day = null, 
        DateTimeZone|string|null $timezone = null
    ): self
    {
        return static::createFrom($year, $month, $day, null, null, null, $timezone);
    }

    /**
     * Returns a new instance with the date set to today, and the time set to the values passed in.
     *
     * @param int|null $hour Hour to pass.
     * @param int|null $minutes Minutes to pass.
     * @param int|null $seconds Seconds to pass.
     * @param DateTimeZone|string|null $timezone Optional timezone to associate with current DateTime instance.
     *
     * @return self Return new DateTimeImmutable object.
     * @throws DatetimeException Throws if any error occurred.
     */
    public static function fromTime(
        ?int $hour = null, 
        ?int $minutes = null, 
        ?int $seconds = null, 
        DateTimeZone|string|null $timezone = null
    ): self
    {
        return static::createFrom(null, null, null, $hour, $minutes, $seconds, $timezone);
    }

    /**
     * Returns a new datetime instance from a relative time format.
     *
     * @param string $datetime Rerative time string (2 days ago, -3 years etc..).
     * @param DateTimeZone|string|null $timezone Optional timezone to associate with current DateTime instance.
     *
     * @return string Return formatted DateTime string.
     * @throws DatetimeException Throws if invalid relative time format was passed.
     */
    public static function fromRelative(string $datetime, DateTimeZone|string|null $timezone = null): string
    {
        if(static::isAgo($datetime)){
            $now = static::agoToDatetime($datetime, $timezone);
        }elseif(static::isRelative($datetime)){
            $now = new DateTime('now', $timezone);
            $now->modify($datetime);
        }else{
            $now = new DateTime('now', $timezone);
            if($now->modify($datetime) === false){
                throw new DatetimeException('Error Invalid relative time "' . $datetime . '" passed');
            }
        }

        $datetime = $now->format(self::$defaultFormat);

        return $datetime;
    }

    /**
     * Returns a new instance with the date time values individually set.
     *
     * @param int|null $year Year to pass.
     * @param int|null $month Month to pass.
     * @param int|null $day Day to pass.
     * @param int|null $hour Hour to pass.
     * @param int|null $minutes Minutes to pass.
     * @param int|null $seconds Seconds to pass.
     * @param DateTimeZone|string|null $timezone Optional timezone to associate with current DateTime instance.
     *
     * @return self Return new DateTimeImmutable object.
     * @throws DatetimeException Throws if any error occurred.
     */
    public static function createFrom(
        ?int $year = null, 
        ?int $month = null, 
        ?int $day = null, 
        ?int $hour = null, 
        ?int $minutes = null, 
        ?int $seconds = null, 
        DateTimeZone|string|null $timezone = null
    ): self 
    {
        $year ??= date('Y');
        $month ??= date('m');
        $day ??= date('d');
        $hour ??= 0;
        $minutes ??= 0;
        $seconds ??= 0;

        return new self(date(self::$defaultFormat, strtotime("{$year}-{$month}-{$day} {$hour}:{$minutes}:{$seconds}")), $timezone);
    }

    /**
     * Returns an instance set to midnight tomorrow morning.
     *
     * @param DateTimeZone|string|null $timezone Optional timezone to associate with current DateTime instance.
     *
     * @return self Return new DateTimeImmutable object.
     * @throws DatetimeException Throws if any error occurred.
     */
    public static function tomorrow(DateTimeZone|string|null $timezone = null): self
    {
        return new self(date('Y-m-d 00:00:00', strtotime('+1 day')), $timezone);
    }

    /**
     * Create new DateTimeImmutable object formatted according to the specified format.
     *
     * @param string $format Format to convert to
     * @param string $datetime datetime to convert to format.
     * @param DateTimeZone|string|null $timezone Optional timezone to associate with current DateTime instance.
     *
     * @return DateTimeImmutable|false  Returns new DateTimeImmutable false otherwise
     * @throws DatetimeException Throws if any error occurred.
    */
    public static function fromFormat(
        string $format, 
        string $datetime, 
        DateTimeZone|string|null $timezone = null
    ): DateTimeImmutable|false
    {
        return parent::createFromFormat($format, $datetime, $timezone);
    }

    /**
     * Returns an array representation of the given calendar month.
     * The array values are timestamps which allow you to easily format.
     * 
     * @param ?string $month Month default is `date('n)`.
     * @param ?string $year Year default is `date('y')`.
     * @param DateTimeZone|string|null $timezone Optional timezone to associate with current DateTime instance.
     * 
     * @return array<int,mixed> $calendar Calendar values.
    */
    public static function calendar(
        ?string $month = null, 
        ?string $year = null, 
        DateTimeZone|string|null $timezone = null
    ): array
    {
        $month ??= date('n');
        $year ??= date('Y');

        $firstDay = new self("$year-$month-01", $timezone); 
        $lastDay = new self($firstDay->format('Y-m-t'), $timezone);
        
        $firstDay->setTime(0, 0, 0);
        $lastDay->setTime(23, 59, 59);

        $start = $firstDay->modify('last Sunday')->getTimestamp();
        $stop = $lastDay->modify('next Sunday')->getTimestamp();

        $calendar = [];
        while ($start < $stop)
        {
            $week = range($start, min($stop, $start + 6 * 86400), 86400);
            $calendar[] = $week;
            $start += 7 * 86400; 
        }

        return $calendar;
    }

    /**
	 * Get an array of dates for each day in a specific month.
	 *
	 * @param string|int|null $month The month (1-12).
	 * @param string|int|null $year The year (e.g., 2023).
	 * @param string $format The format for the returned dates (default is 'd-M-Y').
	 * 
	 * @return array<int,string> An array of dates within the specified month.
	*/
	public static function days(
        string|int|null $month = null, 
        string|int|null $year = null, 
        string $format = 'd-M-Y'
    ): array 
	{
        $month ??= date('M');
        $year ??= date('Y');

		$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$days = [];

		for ($day = 1; $day <= $daysInMonth; $day++) {
			$days[] = date($format, mktime(0, 0, 0, $month, $day, $year));
		}

		return $days;
	}

    /**
	 * Get an array of months for each month in a specific year.
     * 
	 * @param string $format The format for the returned dates (default is "M").
	 * 
	 * @return array<int,string> An array of month within the specified year.
	*/
    public static function months(string $format = 'M'): array 
    {
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $timestamp = mktime(0, 0, 0, $month, 1, null);
            $months[$month] = date($format, $timestamp);
        }

        return $months;
    }

    /**
     * Generate a list of years between a start and end year.
     *
     * @param int|string|null $start Starting year. Defaults to the current year if not provided.
     * @param int|string|null $end Ending year. Defaults to the current year minus 3 years if not provided.
     * 
     * @return array<int, int> List of years.
    */
    public static function years(int|string|null $start = null, int|string|null $end = null): array 
    {
        $start ??= date('Y');
        $end ??= date('Y', strtotime('-3 years'));
        
        // If $end is 0, set it to the current year
        $end = ($end === 0) ? date('Y') : $end;
        $start = is_numeric($start) ? intval($start) : intval(date('Y'));
        $end = is_numeric($end) ? intval($end) : intval(date('Y'));

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
     * Get a list of time hours in 12-hour format with customizable intervals.
     *
     * @param int $interval The interval in minutes. Default is 30.
     * 
     * @return array<int,string> An array of time hours.
     */
    public static function hours(int $interval = 30): array 
    {
        $stepSize = $interval * 60;
        $maxOneDay = 24 * 60 * 60; 
        $steps = range(0, $maxOneDay, $stepSize);

        $timeHours = array_map(function ($timestamp) {
            return date('g:iA', $timestamp);
        }, $steps);

        return $timeHours;
    }

    /**
     * Convert datetime to relative a human-readable representation of the time elapsed since the given datetime.
     *
     * @param string|int|Time|DateTimeImmutable $datetime The datetime string, Unix timestamp, or time string.
     *
     * @return string A string representing the time elapsed since the given datetime, in human-readable format.
     *
     * > If a string is provided, it must be a valid datetime string or time string.
    */
    public static function ago(string|int|Time|DateTimeImmutable $datetime): string 
    {
        if ($datetime instanceof Time) {
            $datetime = $datetime->getTimestamp();
        }elseif($datetime instanceof DateTime || $datetime instanceof DateTimeImmutable) {
            $datetime = $datetime->getTimestamp();
        }elseif(is_string($datetime)) {
            $datetime = strtotime($datetime);
        }

        $now = static::now()->getTimestamp();
        $elapsed = $now - $datetime;

        if ($elapsed <= 60) {
            return "just now";
        }

        $units = [
            29030400 => ['year', 'years'],
            2419200 => ['month', 'months'],
            604800 => ['week', 'weeks'],
            86400 => ['day', 'days'],
            3600 => ['hour', 'hours'],
            60 => ['minute', 'minutes']
        ];

        foreach ($units as $interval => [$singular, $plural]) {
            if ($elapsed <= $interval) {
                $quantity = round($elapsed / $interval);
                return sprintf('%d %s%s ago', $quantity, $quantity == 1 ? $singular : $plural, $quantity == 1 ? '' : 's');
            }
        }

        // If none of the intervals match, return the years ago
        $quantity = round($elapsed / 29030400);
        return sprintf('%d year%s ago', $quantity, $quantity == 1 ? '' : 's');
    }

    /**
     * Convert relative time ago to datetime instance.
     * 
     * @param string $ago Social time ago.
     * @param DateTimeZone|string|null $timezone Optional timezone to associate with current DateTime instance.
     * 
     * @return DateTime Return new datetime instance.
     * @throws DateTimeException If invalid time unit was found in the ago format.
    */
    public static function agoToDatetime(string $ago, DateTimeZone|string|null $timezone = null): DateTime
    {
        $ago = strtolower(trim($ago));
        $timezone = static::timezone($timezone);

        if ($ago === 'just now') {
            return new DateTime('now', $timezone);
        }

        $parts = explode(' ', $ago, 3);
        if (count($parts) !== 3 || strtolower($parts[2]) !== 'ago') {
            throw new DateTimeException('Invalid relative time format');
        }

        $quantity = (int) $parts[0];
        $unit = strtolower(rtrim($parts[1], 's')); 

        $intervals = [
            'second' => 'PT%dS',
            'minute' => 'PT%dM',
            'hour' => 'PT%dH',
            'day' => 'P%dD',
            'week' => 'P%dW',
            'month' => 'P%dM',
            'year' => 'P%dY',
        ];

        if (!isset($intervals[$unit])) {
            throw new DateTimeException('Invalid time unit: ' . $unit);
        }

        $pattern = sprintf($intervals[$unit], $quantity);
        $timeInterval = new DateInterval($pattern);

        $dateTime = new DateTime('now', $timezone);
        $dateTime->sub($timeInterval);

        return $dateTime;
    }

    /**
	 * Check if a certain amount of minutes has passed since the given timestamp.
	 *
	 * @param string|int|Time|DateTimeImmutable $datetime Either a Unix timestamp, DateTimeImmutable or a string representing a date/time.
	 * @param int $minutes The number of minutes to check against.
	 *
	 * @return bool True if the specified minutes have passed, false otherwise.
     * @throws DateTimeException If invalid datetime was passed.
	 */
	public static function passed(string|int|Time|DateTimeImmutable $datetime, int $minutes, null|DateTimeZone|string $timezone = 'UTC'): bool 
	{
        if (is_numeric($datetime)) {
            $datetime = static::parse("@$datetime", $timezone);
        } elseif (!($datetime instanceof Time || $datetime instanceof DateTime || $datetime instanceof DateTimeImmutable)) {
            $datetime = static::fromFormat(static::$defaultFormat, $datetime, $timezone);
        }
        
        $timestamp = $datetime->getTimestamp();
       
        if ($timestamp === false || $timestamp === 0) {
            throw new DateTimeException('Invalid datetime "' . $datetime . '" specified');
        }
    
        $now = static::now($timezone);
		$interval = $now->getTimestamp() - $timestamp;
		$difference = $interval / 60;

        /*
        $interval = $now->diff($datetime);
        $difference = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
        */

		return $difference >= $minutes;
	}

    /**
     * Get the suffix for a given day (e.g., 1st, 2nd, 3rd, 4th).
     *
     * @param string|int $day The day for which to determine the suffix.
     * 
     * @return string The day with its appropriate suffix.
    */
    public static function suffix(string|int $day): string 
    {
        $day = is_string($day) ? (int) $day : $day;

        $lastDigit = $day % 10;
        $lastTwoDigits = $day % 100;

        if ($lastDigit === 1 && $lastTwoDigits !== 11) {
            return $day . "st";
        } 

        if ($lastDigit === 2 && $lastTwoDigits !== 12) {
            return $day . "nd";
        }
        
        if ($lastDigit === 3 && $lastTwoDigits !== 13) {
            return $day . "rd";
        } 

        return $day . "th";
    }


    /**
     * Check if time string has a relative time keywords.
     * 
     * @param string $datetime The datetime string to check.
     * 
     * @return bool True if the string contains relative time keywords otherwise, false.
    */
    public static function isRelative(string $datetime): bool
    {
        return preg_match(static::$relativePattern, $datetime) === 1;
    }

    /**
     * Check if time string has a human-readable relative time keywords from `ago` method.
     * 
     * @param string $datetime The datetime string to check.
     * 
     * @return bool True if the string contains relative time keywords otherwise, false.
    */
    public static function isAgo(string $datetime): bool
    {
        return preg_match(static::$agoRelativePattern, $datetime) === 1;
    }

    /**
     * Check if time string is a valid absolute time string.
     * 
     * @param string $datetime The datetime string to check.
     * 
     * @return bool True if the string contains absolute time time otherwise, false.
    */
    public static function isAbsolute(string $datetime): bool
    {
        if (preg_match('/\d{4}-\d{1,2}-\d{1,2}/', $datetime)) {
            return true;
        }

        return false;
    }

    /**
     * Wakeup is called during unserializing the Time object.
    */
    public function __wakeup(): void
    {
        $timezone = (string) $this->timezone;

        $this->timezone = new DateTimeZone($timezone);
        parent::__construct('now', $this->timezone);
    }

    /**
     * Magic getter method to allow access to properties.
     *
     * @param string $name Method name to get
     *
     * @return array|bool|DateTimeInterface|DateTimeZone|int|DateTime|self|string|null
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
     * Allow for property-type checking to any getX method...
     *
     * @param string $name Method name to get
     * 
     * @return bool Return true if method exisit.
     */
    public function __isset(string $name): bool
    {
        $method = 'get' . ucfirst($name);

        return method_exists($this, $method);
    }
}