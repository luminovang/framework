<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utility;

final class Mask
{
	/**
     * Mask a string by position.
     *
     * @param string $string String to mask.
     * @param string $masker Mask character (default: "*").
     * @param string $position Masking position ("center", "left", or "right").
     * @param int|null $limit Maximum number of characters to mask.
     *
     * @return string Return masked string.
     * @see Mask::email() To mask an email address.
     */
    public static function position(
        string $string,
        string $masker = '*',
        string $position = 'center',
        ?int $limit = null
    ): string 
    {
        if ($string === '') {
            return '';
        }

        $length = strlen($string);

        if ($limit === null) {
            $limit = (int) round($length / 4);
        }

        $limit = min(max($limit, 0), $length);

        if ($position === 'left') {
            return str_repeat($masker, $limit)
                . substr($string, $limit);
        }

        if ($position === 'right') {
            return substr($string, 0, $length - $limit)
                . str_repeat($masker, $limit);
        }

        $left = (int) floor(($length - $limit) / 2);

        return substr($string, 0, $left)
            . str_repeat($masker, $limit)
            . substr($string, $left + $limit);
    }

    /**
     * Mask an email address while keeping visible characters.
     *
     * @param string $email Email address to mask.
     * @param string $masker Mask character (default: "*").
     * @param int $visible Number of visible characters before masking.
     *
     * @return string Return the masked email address.
     * @see Mask::position() To mask any string.
     */
    public static function email(
        string $email,
        string $masker = '*',
        int $visible = 2
    ): string 
    {
        if ($email === '') {
            return '';
        }

        $parts = explode('@', $email, 2);

        if (count($parts) !== 2) {
            return $email;
        }

        [$name, $domain] = $parts;

        $length = strlen($name);

        if ($length <= $visible) {
            return $name . '@' . $domain;
        }

        return substr($name, 0, $visible)
            . str_repeat($masker, $length - $visible)
            . '@'
            . $domain;
    }

    /**
     * Mask a phone number while keeping the last digits visible.
     *
     * @param string $phone Phone number to mask.
     * @param string $masker Mask character.
     * @param int $visible Number of visible ending digits.
     *
     * @return string
     */
    public static function phone(
        string $phone,
        string $masker = '*',
        int $visible = 4
    ): string 
    {
        $length = strlen($phone);

        if ($length <= $visible) {
            return $phone;
        }

        $maskLength = $length - $visible;

        return str_repeat($masker, $maskLength)
            . substr($phone, -$visible);
    }

    /**
     * Mask a credit card number while keeping the last digits visible.
     *
     * @param string $card Credit card number to mask.
     * @param string $masker Mask character.
     * @param int $visible Number of visible ending digits.
     *
     * @return string
     */
    public static function card(
        string $card,
        string $masker = '*',
        int $visible = 4
    ): string 
    {
        $clean = preg_replace('/\D+/', '', $card);

        if ($clean === null || strlen($clean) <= $visible) {
            return $card;
        }

        $maskedLength = strlen($clean) - $visible;

        $masked = str_repeat($masker, $maskedLength)
            . substr($clean, -$visible);

        return trim(chunk_split($masked, 4, ' '));
    }

    /**
	 * Mask a password for safe display (e.g. in logs).
	 * 
	 * This method replaces all but the last *N* characters with `*`.
	 *
	 * @param string $password Password to mask.
	 * @param int $visible The number of last characters to leave visible (minimum: 0).
	 * 
	 * @return string Returns a masked password string.
	 * 
	 * > Use this when logging or debugging sensitive data without revealing full passwords.
	 */
	public static function password(string $password, int $visible = 3): string 
	{
		$len = strlen($password);

		if ($len === 0) {
			return '';
		}

		$visible = max(0, $visible);

		if ($visible >= $len) {
			return str_repeat('*', $len);
		}

		return str_repeat('*', $len - $visible) 
            . substr($password, -$visible);
	}
}