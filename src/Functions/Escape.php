<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Functions;

use \Laminas\Escaper\Escaper;
use \Luminova\Exceptions\BadMethodCallException;
use \Luminova\Exceptions\RuntimeException;

class Escape
{
    /**
     * @var class-object<Escaper> $escaper Escaper object
    */
    private ?object $escaper = null;

    /**
     * @var string $encoding Escaper encoding
    */
    protected string $encoding = 'utf-8';

    /**
     * @var int $encodingFlags Escaper encoding flag
    */
    protected int $encodingFlags = 0;

     /**
     * @var string[] $supportedEncodings Escaper supported encodings
    */
    protected array $supportedEncodings = [
        'iso-8859-1',
        'iso8859-1',
        'iso-8859-5',
        'iso8859-5',
        'iso-8859-15',
        'iso8859-15',
        'utf-8',
        'cp866',
        'ibm866',
        '866',
        'cp1251',
        'windows-1251',
        'win-1251',
        '1251',
        'cp1252',
        'windows-1252',
        '1252',
        'koi8-r',
        'koi8-ru',
        'koi8r',
        'big5',
        '950',
        'gb2312',
        '936',
        'big5-hkscs',
        'shift_jis',
        'sjis',
        'sjis-win',
        'cp932',
        '932',
        'euc-jp',
        'eucjp',
        'eucjp-win',
        'macroman',
    ];

    /**
     * Input escaper constructor.
     * 
     * @param string|null $encoding The character encoding to use. Defaults to 'utf-8'.
     */
    public function __construct(string|null $encoding = 'utf-8')
    {
        if($encoding !== null){
            $encoding = strtolower($encoding);
            if (!in_array($encoding, $this->supportedEncodings)) {
                $encoding = 'utf-8';
            }

            $this->encoding = $encoding;
        }
        $this->encodingFlags = ENT_QUOTES | ENT_SUBSTITUTE;

        if (class_exists(Escaper::class)) {
            $this->escaper = new Escaper($encoding);
        }
    }

    /**
     * Magic method to handle method calls dynamically.
     * 
     * @param string $name The name of the method being called.
     * @param array $arguments The arguments passed to the method.
     * 
     * @return mixed The result of the method call.
     * @throws BadMethodCallException When the called method does not exist.
     */
    public function __call(string $name, array $arguments): mixed
    {
        if ($this->escaper === null) {
            if (method_exists($this, $name)) {
                return $this->{$name}(...$arguments);
            }

            throw new BadMethodCallException('Method ' . $name . ' does not exist, to use ' . $name . ', you need to install a third-party library first. Run "composer require laminas/laminas-escaper"');
        }

        return $this->escaper->{$name}(...$arguments);
    }

    /**
     * Get the character encoding used by the escaper.
     * 
     * @return string The character encoding.
     */
    protected function getEncoding(): string
    {
        return $this->encoding;
    }

    /**
     * Escape HTML special characters.
     * 
     * @param string $string The string to be escaped.
     * 
     * @return string Return the escaped string.
     */
    protected function escapeHtml(string $string): string
    {
        return htmlspecialchars($string, $this->encodingFlags, $this->encoding);
    }

    /**
     * Escape HTML attribute values.
     * 
     * @param string $string The string to be escaped.
     * 
     * @return string Return the escaped string.
     */
    protected function escapeHtmlAttr(string $string): string
    {
        return htmlspecialchars($string, $this->encodingFlags, $this->encoding);
    }

    /**
     * Escape JavaScript special characters.
     * 
     * @param array|string $string The string or array of strings to be escaped.
     * 
     * @return string The escaped string or array of strings.
     */
    protected function escapeJs(array|string $string): string
    {
        return str_replace(
            ['<', '>', '\'', '"', '&', '\\'],
            ['\x3c', '\x3e', '\x27', '\x22', '\x26', '\x5c'],
            $string
        );
    }

    /**
     * Escape CSS special characters.
     * 
     * @param string $string The string to be escaped.
     * 
     * @return string Return the escaped string.
     */
    protected function escapeCss(string $string): string
    {
        return preg_replace('/[^\w\s]/i', '\\\$0', $string);
    }

    /**
     * Convert a string to UTF-8 encoding.
     * 
     * @param string $string The string to be converted.
     * 
     * @return string Return the converted string.
     * @throws RuntimeException When the string is not valid UTF-8 or cannot be converted.
     */
    protected function toUtf8(string $string): string
    {

        $result = $this->encoding === 'utf-8' ? $string : $this->convertEncoding($string, 'UTF-8', $this->encoding);

        if (!is_utf8($result)) {
            throw new RuntimeException(
                sprintf('String to be escaped was not valid UTF-8 or could not be converted: %s', $result)
            );
        }

        return $result;
    }

    /**
     * Convert a string from UTF-8 encoding.
     * 
     * @param string $string The string to be converted.
     * @return string Return the converted string.
     */
    protected function fromUtf8(string $string): string
    {
        return ($this->encoding === 'utf-8') ? $string : $this->convertEncoding($string, $this->encoding, 'UTF-8');
    }

    /**
     * Convert a string to a different character encoding.
     * 
     * @param array|string $string The string or array of strings to be converted.
     * @param string $to The target character encoding.
     * @param array|string|null $from The source character encoding. Defaults to null (auto-detection).
     * 
     * @return string Return the converted string.
     */
    protected function convertEncoding(array|string $string, string $to, null|array|string $from = null): string
    {
        $result = mb_convert_encoding($string, $to, $from);

        if ($result === false) {
            return '';
        }

        return $result;
    }
}