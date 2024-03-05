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
use \Locale;
use \DateTime;
use \IntlDateFormatter;
use \ReturnTypeWillChange;
use \DateTimeInterface;

class Time extends DateTimeImmutable
{
    /**
     * @var DateTimeZone
     */
    protected ?DateTimeZone $timezone = null;

    /**
     * @var ?string
     */
    protected ?string $locale;

     /**
     * Format to use when displaying datetime through __toString
     *
     * @var string
     */
    protected $toStringFormat = 'yyyy-MM-dd HH:mm:ss';

     /**
     * Default Format to use w
     *
     * @var string $defaultFormat
     */
    protected static $defaultFormat = 'Y-m-d H:i:s';

    /**
     *
     * @var string $relativePattern relative time keywords pattern
     */
    protected static string $relativePattern = '/this|next|last|tomorrow|yesterday|midnight|today|[+-]|first|last|ago/i';

    /**
     * Time constructor.
     *
     * @param ?string $time
     * @param DateTimeZone|string|null $timezone
     * @param ?string $local 
     *
     * @throws Exception
     */
    public function __construct(?string $time = null, DateTimeZone|string|null $timezone = null, ?string $locale = null)
    {
        $this->locale = $locale;

        if($this->locale === null && class_exists('\Locale')){
            $this->locale =  Locale::getDefault();
        }

        $this->locale ??= '';
        $time ??= '';
        $timezone ??= date_default_timezone_get();

        $this->timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);

        if ($time !== '' && static::isRelative($time)) {
            $instance = new DateTime('now', $this->timezone);
            $instance->modify($time);
            $time = $instance->format(self::$defaultFormat);
        }

        parent::__construct($time, $this->timezone);
    }

    /**
     * Set the TimeZone associated with the DateTime
     * And Returns a new instance with the revised timezone.
     *
     * @param DateTimeZone|string $timezone
     *
     * @return self
     * @throws Exception
     */
    #[ReturnTypeWillChange]
    public function setTimezone($timezone): self
    {
        $timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);

        return self::fromInstance($this->toDateTime()->setTimezone($timezone), $this->locale);
    }

    /**
     * Returns current Time instance with the timezone set.
     *
     * @param DateTimeZone|string|null $timezone
     * @param ?string $local 
     *
     * @return self
     *
     * @throws Exception
     */
    public static function now(DateTimeZone|string|null $timezone = null, ?string $locale = null): self
    {
        return new self(null, $timezone, $locale);
    }

     /**
     * Returns a new Time instance while parsing a datetime string.
     *
     * Example:
     *  $time = Time::parse('first day of December 2008');
     *
     * @param string $datetime 
     * @param DateTimeZone|string|null $timezone
     * @param ?string $local 
     *
     * @return self
     *
     * @throws Exception
     */
    public static function parse(string $datetime, DateTimeZone|string|null $timezone = null, ?string $locale = null): self
    {
        return new self($datetime, $timezone, $locale);
    }

    /**
     * Return a new time with the time set to midnight.
     *
     * @param DateTimeZone|string|null $timezone
     * @param ?string $local 
     *
     * @return self
     *
     * @throws Exception
     */
    public static function today(DateTimeZone|string|null $timezone = null, ?string $locale = null): self
    {
        return new self(date('Y-m-d 00:00:00'), $timezone, $locale);
    }

    /**
     * Returns an instance set to midnight yesterday morning.
     *
     * @param DateTimeZone|string|null $timezone
     * @param ?string $local 
     *
     * @return self
     *
     * @throws Exception
     */
    public static function yesterday(DateTimeZone|string|null $timezone = null, ?string $locale = null): self
    {
        return new self(date('Y-m-d 00:00:00', strtotime('-1 day')), $timezone, $locale);
    }

     /**
     * Returns the localized Year
     *
     * @throws Exception
     */
    public function getYear(): string
    {
        return $this->toLocalizedString('y');
    }

    /**
     * Returns the localized Month
     *
     * @throws Exception
     */
    public function getMonth(): string
    {
        return $this->toLocalizedString('M');
    }

    /**
     * Return the localized day of the month.
     *
     * @throws Exception
     */
    public function getDay(): string
    {
        return $this->toLocalizedString('d');
    }

    /**
     * Return the localized hour (in 24-hour format).
     *
     * @throws Exception
     */
    public function getHour(): string
    {
        return $this->toLocalizedString('H');
    }

    /**
     * Return the localized minutes in the hour.
     *
     * @throws Exception
     */
    public function getMinute(): string
    {
        return $this->toLocalizedString('m');
    }

    /**
     * Return the localized seconds
     *
     * @throws Exception
     */
    public function getSecond(): string
    {
        return $this->toLocalizedString('s');
    }


    /**
     * Return the index of the day of the year
     *
     * @throws Exception
     */
    public function getDayOfYear(): string
    {
        return $this->toLocalizedString('D');
    }

    /**
     * Return the index of the week in the month
     *
     * @throws Exception
     */
    public function getWeekOfMonth(): string
    {
        return $this->toLocalizedString('W');
    }

    /**
     * Return the index of the week in the year
     *
     * @throws Exception
     */
    public function getWeekOfYear(): string
    {
        return $this->toLocalizedString('w');
    }

     /**
     * Return the index of the day of the week
     *
     * @throws Exception
     */
    public function getDayOfWeek(): string
    {
        return $this->toLocalizedString('c');
    }

    /**
     * Returns the number of the current quarter for the year.
     *
     * @throws Exception
     */
    public function getQuarter(): string
    {
        return $this->toLocalizedString('Q');
    }

    /**
     * Are we in daylight savings time currently?
     */
    public function isDaylightSaving(): bool
    {
        return $this->format('I') === '1'; 
    }

    /**
     * Returns boolean whether the passed timezone is the same as
     * the local timezone.
     */
    public function isSameLocal(): bool
    {
        $local = date_default_timezone_get();

        return $local === $this->timezone->getName();
    }

    /**
     * Returns boolean whether object is in UTC.
     */
    public function getUtc(): bool
    {
        return $this->getOffset() === 0;
    }

    /**
     * Returns the name of the current timezone.
     */
    public function getTimezoneName(): string
    {
        return $this->timezone->getName();
    }

     /**
     * Returns a Time instance with the timezone converted to UTC.
     *
     * @param DateTimeInterface|self|string $time
     *
     * @return DateTime|static
     *
     * @throws Exception
     */
    public function getUTCObject($time, ?string $timezone = null)
    {
        if ($time instanceof self) {
            $time = $time->toDateTime();
        } elseif (is_string($time)) {
            $timezone = $timezone ?: $this->timezone;
            $timezone = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
            $time     = new DateTime($time, $timezone);
        }

        if ($time instanceof DateTime || $time instanceof DateTimeImmutable) {
            $time = $time->setTimezone(new DateTimeZone('UTC'));
        }

        return $time;
    }


     /**
     * Returns the localized value of this instance in $format.
     *
     * @return false|string
     *
     * @throws Exception
     */
    public function toLocalizedString(?string $format = null)
    {
        $format ??= $this->toStringFormat;

        return IntlDateFormatter::formatObject($this->toDateTime(), $format, $this->locale);
    }

    /**
     * Converts the current instance to a mutable DateTime object.
     *
     * @return DateTime
     *
     * @throws Exception
     */
    public function toDateTime()
    {
        $dateTime = new DateTime('', $this->getTimezone());
        $dateTime->setTimestamp(parent::getTimestamp());

        return $dateTime;
    }

    /**
     * Takes an instance of DateTimeInterface and returns an instance of Time with it's same values.
     *
     * @return self
     *
     * @throws Exception
     */
    public static function fromInstance(DateTimeInterface $dateTime, ?string $locale = null)
    {
        $date = $dateTime->format(self::$defaultFormat);
        $timezone = $dateTime->getTimezone();

        return new self($date, $timezone, $locale);
    }

    /**
     * Returns a new instance with the datetime set based on the provided UNIX timestamp.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return self
     *
     * @throws Exception
     */
    public static function fromTimestamp(int $timestamp, $timezone = null, ?string $locale = null)
    {
        $time = new self(gmdate(self::$defaultFormat, $timestamp), 'UTC', $locale);

        $timezone ??= date_default_timezone_get();

        return $time->setTimezone($timezone);
    }

     /**
     * Returns a new instance based on the year, month and day. If any of those three
     * are left empty, will default to the current value.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return self
     *
     * @throws Exception
     */
    public static function fromDate(?int $year = null, ?int $month = null, ?int $day = null, $timezone = null, ?string $locale = null)
    {
        return static::createFrom($year, $month, $day, null, null, null, $timezone, $locale);
    }

    /**
     * Returns a new instance with the date set to today, and the time set to the values passed in.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return self
     *
     * @throws Exception
     */
    public static function fromTime(?int $hour = null, ?int $minutes = null, ?int $seconds = null, $timezone = null, ?string $locale = null)
    {
        return static::createFrom(null, null, null, $hour, $minutes, $seconds, $timezone, $locale);
    }

    /**
     * Returns a new instance with the date time values individually set.
     *
     * @param DateTimeZone|string|null $timezone
     *
     * @return self
     *
     * @throws Exception
     */
    public static function createFrom(?int $year = null, ?int $month = null, ?int $day = null, ?int $hour = null, ?int $minutes = null, ?int $seconds = null, $timezone = null, ?string $locale = null) {
        $year ??= date('Y');
        $month ??= date('m');
        $day ??= date('d');
        $hour ??= 0;
        $minutes ??= 0;
        $seconds ??= 0;

        return new self(date(self::$defaultFormat, strtotime("{$year}-{$month}-{$day} {$hour}:{$minutes}:{$seconds}")), $timezone, $locale);
    }

    /**
     * Returns an instance set to midnight tomorrow morning.
     *
     * @param DateTimeZone|string|null $timezone
     * @param ?string $local 
     *
     * @return self
     *
     * @throws Exception
     */
    public static function tomorrow(DateTimeZone|string|null $timezone = null, ?string $locale = null)
    {
        return new self(date('Y-m-d 00:00:00', strtotime('+1 day')), $timezone, $locale);
    }


     /**
     * Provides a replacement for DateTime's own createFromFormat function, that provides
     * more flexible timeZone handling
     *
     * @param string                   $format
     * @param string                   $datetime
     * @param DateTimeZone|string|null $timezone
     *
     * @return self
     *
     * @throws Exception
     */
    #[ReturnTypeWillChange]
    public static function fromFormat(string $format, string $time, DateTimeZone|string|null $timezone = null): self
    {
        return parent::createFromFormat($format, $time, $timezone);
    }

    /**
     * Returns an array representation of the given calendar month.
     * The array values are timestamps which allow you to easily format
     * 
     * @param ?string $month date('n)
     * @param ?string $year date('y')
     * 
     * @return array $calendar
    */
   public static function calendar(?string $month = null, ?string $year = null, DateTimeZone|string|null $timezone = null, ?string $locale = null): array
    {
        $month ??= date('n');
        $year ??= date('Y');

        ////new DateTime()
        $firstDay = new self("$year-$month-01", $timezone, $locale); 
        $lastDay = new self($firstDay->format('Y-m-t'), $timezone, $locale);
        
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
	 * @param string|int|null $year The year.
	 * @param string $format The format for the returned dates (default is "d-M-Y").
	 * 
	 * @return array An array of dates within the specified month.
	 */
	public static function days(string|int|null $month = null, string|int|null $year = null, string $format = "d-M-Y"): array 
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
     * Get a list of time hours in 12-hour format with customizable intervals.
     *
     * @param int $interval The interval in minutes. Default is 30.
     * 
     * @return array An array of time hours.
     */
    public static function hours(int $interval = 30): array 
    {
        $formatTime = function ($timestamp) {
            return date('g:iA', $timestamp);
        };

        $stepSize = $interval * 60;
        $maxOneDay = 24 * 60 * 60; 
        $steps = range(0, $maxOneDay, $stepSize);

        $timeHours = array_map($formatTime, $steps);

        return $timeHours;
    }


    /**
	 * Converts a PHP timestamp to a social media-style time format (e.g., "2 hours ago").
	 *
	 * @param string|int $time The timestamp to convert.
     * 
	 * @return string Time in a human-readable format.
	 */
	public static function ago(string|int $time): string 
	{
		$elapsed = time() - strtotime((string) $time);

		return match (true) {
			$elapsed <= 60 => "just now",
			$elapsed <= 3600 => sprintf('%d minute%s ago', round($elapsed / 60), (round($elapsed / 60) == 1) ? '' : 's'),
			$elapsed <= 86400 => sprintf('%d hour%s ago', round($elapsed / 3600), (round($elapsed / 3600) == 1) ? '' : 's'),
			$elapsed <= 604800 => sprintf('%d day%s ago', round($elapsed / 86400), (round($elapsed / 86400) == 1) ? '' : 's'),
			$elapsed <= 2419200 => sprintf('%d week%s ago', round($elapsed / 604800), (round($elapsed / 604800) == 1) ? '' : 's'),
			$elapsed <= 29030400 => sprintf('%d month%s ago', round($elapsed / 2419200), (round($elapsed / 2419200) == 1) ? '' : 's'),
			default => sprintf('%d year%s ago', round($elapsed / 29030400), (round($elapsed / 29030400) == 1) ? '' : 's'),
		};
	}

    /**
	 * Check if a certain amount of minutes has passed since the given timestamp.
	 *
	 * @param int|string $timestamp Either a Unix timestamp or a string representing a date/time.
	 * @param int $minutes The number of minutes to check against.
	 *
	 * @return bool True if the specified minutes have passed, false otherwise.
	 */
	public static function passed(string|int $timestamp, int $minutes): bool 
	{
		if (is_numeric($timestamp)) {
			$timestamp = (int) $timestamp;
		} else {
			$timestamp = strtotime($timestamp);
			if ($timestamp === false) {
				return false;
			}
		}

		$timeDifference = time() - $timestamp;
		$minutesDifference = $timeDifference / 60;

		return $minutesDifference >= $minutes;
	}

    /**
     * Get the suffix for a given day (e.g., 1st, 2nd, 3rd, 4th).
     *
     * @param string|int $day The day for which to determine the suffix.
     * 
     * @return string The day with its appropriate suffix.
     */
    public static function daySuffix(string|int $day): string 
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
     * Used to check time string to determine if it has relative time keywords
     * 
     * @param string $time 
     * 
     * @return bool
     */
    public static function isRelative(string $time): bool
    {
        if (!preg_match('/\d{4}-\d{1,2}-\d{1,2}/', $time)) {
            return preg_match(static::$relativePattern, $time) > 0;
        }

        return false;
    }

}