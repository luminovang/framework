<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Builder;

use \Luminova\Time\Time;
use \Luminova\Exceptions\DateTimeException;
use \DateTimeZone;

class Document
{
    /**
     * Default html5 elements.
     * 
     * @var int HTML5
     */
    public const HTML5 = 1;

    /**
     * Html5 elements with bootstrap template.
     * 
     * @var int BOOTSTRAP5
     */
    public const BOOTSTRAP5 = 2;

    /**
     * This property determines whether element tag names should be automatically converted to lowercase.
     * 
     * @var bool $xhtmlStrictTagNames
     */
    public static bool $xhtmlStrictTagNames = true;

    /**
     * The input style to use.
     * 
     * @var int $template
     */
    public static int $template = 1;

    /**
     * Use encoding for escaping input. 
     * 
     * @var string $encoding
     */
    public static string $encoding = 'UTF-8';

    /**
     * List of html document types.
     *
     * @var array<string,string> $docTypes
    */
    public static array $docTypes = [
        'html5'             => '<!DOCTYPE html>',
        'xhtml11'           => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
        'xhtml1-strict'     => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
        'xhtml1-trans'      => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
        'xhtml1-frame'      => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
        'xhtml-basic11'     => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML Basic 1.1//EN" "http://www.w3.org/TR/xhtml-basic/xhtml-basic11.dtd">',
        'mathml2'           => '<!DOCTYPE math PUBLIC "-//W3C//DTD MathML 2.0//EN" "http://www.w3.org/Math/DTD/mathml2/mathml2.dtd">',
        'svg10'             => '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.0//EN" "http://www.w3.org/TR/2001/REC-SVG-20010904/DTD/svg10.dtd">',
        'svg11'             => '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">',
        'html4-strict'      => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">',
        'html4-trans'       => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">',
        'html4-frame'       => '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd">',
        'mathml1'           => '<!DOCTYPE math SYSTEM "http://www.w3.org/Math/DTD/mathml1/mathml.dtd">',
        'svg11-basic'       => '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1 Basic//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11-basic.dtd">',
        'svg11-tiny'        => '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1 Tiny//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11-tiny.dtd">',
        'xhtml-math-svg-xh' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1 plus MathML 2.0 plus SVG 1.1//EN" "http://www.w3.org/2002/04/xhtml-math-svg/xhtml-math-svg.dtd">',
        'xhtml-math-svg-sh' => '<!DOCTYPE svg:svg PUBLIC "-//W3C//DTD XHTML 1.1 plus MathML 2.0 plus SVG 1.1//EN" "http://www.w3.org/2002/04/xhtml-math-svg/xhtml-math-svg.dtd">',
        'xhtml-rdfa-1'      => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML+RDFa 1.0//EN" "http://www.w3.org/MarkUp/DTD/xhtml-rdfa-1.dtd">',
        'xhtml-rdfa-2'      => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML+RDFa 1.1//EN" "http://www.w3.org/MarkUp/DTD/xhtml-rdfa-2.dtd">'
    ];

    /**
     * Supported non-standard HTML5 input types.
     * 
     * @var array $invalidInputTypes
     */
    protected static array $invalidInputTypes = [
        'string'   => 'text',
        'int'      => 'number',
        'integer'  => 'number',
        'datetime' => 'datetime-local',
    ]; 

    /**
     * Retrieve a doctype based on the name-type.
     *
     * @param string $type The type of your document retrieve.
     *
     * @return string|null Returns content doctype based on type.
    */
    public static function doctype(string $type = 'html5'): ?string
    {
        return self::$docTypes[$type] ?? null;
    }

    /**
     * Generate HTML attributes as a string and escape values while generating.
     *
     * @param array<string,string|int|float|bool> $attributes An array of attributes as key-value pairs.
     * 
     * @return string Return the attributes as a string.
     */
    public static function attributes(array $attributes): string
    {
        $attr = '';
        foreach ($attributes as $key => $value) {
            $attr .= ' ' . self::esc($key) . (($value === null) 
                ? '' 
                : '="' . self::getAttrType($value) . '"'
            );
        }

        return $attr;
    }

    /**
     * Generates a calendar for a specified month and year, highlighting important seasonal days.
     * 
     * @param string|null $year The year for which the calendar is generated (default: `currentYear`).
     * @param string|null $month The month for which the calendar is generated (default: `currentMonth`).
     * @param DateTimeZone|string|null $timezone  The timezone to be used for date calculations (default: `systemTimezone`).
     * @param array $season_days An optional array of names representing significant days or events in the season.
     * @param int $season_start_month  The starting month of the season (1-12) (default: `January`).
     * @param int $season_start_day The starting day of the season (1-31) (default: `March`).
     *
     * @return string|false Returns the generated HTML calendar as a string, or false if the calendar cannot be generated.
     * @throws DateTimeException Throws if invalid calendar date format is provided.
     */
    public static function calendar( 
        ?string $year = null, 
        ?string $month = null,
        DateTimeZone|string|null $timezone = null,
        array $season_days = [],
        int $season_start_month = 1,
        int $season_start_day = 3
    ): string|bool
    {
        $month ??= date('n');
        $year ??= date('Y');
        $calendar = Time::calendar($month, $year, $timezone);

        if(!$calendar){
            return false;
        }
        
        static $datetime = null;
        $length = $season_days ? count($season_days) : 0;
        $today =  date('j');
        $datetime ??= $season_days 
            ? new Time(date('Y-m-d', strtotime("{$year}-$season_start_month-$season_start_day")), $timezone) 
            : null;

        $html = <<<HTML
            <table border="1" cellpadding="5" cellspacing="0" class="lmv-calendar-table">
            <thead class="lmv-calendar-head" style="background-color: teal;color: #fff;">
                <tr>
                    <th style="border-color: teal;">Sun</th>
                    <th style="border-color: teal;">Mon</th>
                    <th style="border-color: teal;">Tue</th>
                    <th style="border-color: teal;">Wed</th>
                    <th style="border-color: teal;">Thu</th>
                    <th style="border-color: teal;">Fri</th>
                    <th style="border-color: teal;">Sat</th>
                </tr>
            </thead>
            <tbody>
        HTML;

        foreach ($calendar as $week) {
            $html .= '<tr>';
            foreach ($week as $timestamp) {
                $day = date('j', $timestamp);
                $isCurrentMonth = (date('n', $timestamp) === $month);
                $isCurrentDay = ($day === $today);

                $style = ($isCurrentMonth
                    ?   ($isCurrentDay
                            ? ' lmv-calendar-current" style="color: #fff;background-color: #063ca9;border-color: #063ca9;"' 
                            : '"'
                        ) 
                    : ' lmv-calendar-past" style="color: #ccc;"'
                );

                $html .= '<td class="lmv-calendar-day' . $style . ' data-day="'.$day.'" data-timestamp="'.$timestamp.'">';
                $html .= $day;

                if($datetime){
                    $daysSinceBase = floor(($timestamp - $datetime->getTimestamp()) / 86400);
                    $html .= '<small class="lmv-calendar-season" style="display: block;color: ' . ($isCurrentDay ? '#f4dde0' : ($isCurrentMonth ? '#bf0707' : '#532828')) . ';">' . $season_days[$daysSinceBase % $length] . '</small>';
                }

                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '<tfoot class="lmv-calendar-head" style="background-color: #3a3a3b;color: #fff;">';
        $html .= '<tr><th colspan="7" align="center" style="border-color: #3a3a3b;">' . Time::month($month) . ', ' . $year . ' Calender</th></tr>';
        $html .= '</tfoot>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Escape and retrieve attribute value type.
     *
     * @param mixed $value The value of the attribute.
     * 
     * @return string Return the safely escaped string or numeric value.
     */
    protected static function getAttrType(mixed $value): string 
    {
        if($value === ''){
            return '';
        }

        return match($value){
            true => 'true',
            false => 'false',
            default => self::esc($value)
        };
    }

    /**
     * Escapes input for safe output in HTML, including handling numeric values directly.
     *
     * @param string|int|float $input The value to be escaped.
     * 
     * @return string Return the safely escaped string or numeric value.
     * @ignore
     */
    public static function esc(string|int|float $input): string
    {
        if ($input === '' || is_numeric($input)) {
            return (string) $input;
        }

        $input = trim((string) $input);
        return htmlspecialchars($input, ENT_QUOTES|ENT_SUBSTITUTE, self::$encoding);
    }
}