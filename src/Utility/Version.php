<?php
/**
 * Luminova Framework Version compare helper.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utility;

use Luminova\Exceptions\InvalidArgumentException;

final class Version
{
    /**
     * @var string VERSION
     */
    private const VERSION = '\d+(\.\d+){0,2}';

    /**
     * @var array PATTERNS
     */
    private const PATTERNS = [
        self::VERSION,                                 // 3.8
        '\^' . self::VERSION,                          // ^3.8
        '~' . self::VERSION,                           // ~3.8
        '(>=|<=|>|<|==|=|!=|<>)\s*' . self::VERSION,   // >=3.8
    ];

    /**
     * Cached parsed constraint parts.
     *
     * @var array<string, array<int, string>>
     */
    private static array $cache = [];

    /**
     * Check if a version satisfies a constraint expression.
     *
     * **Supports:**
     * - ^3.0
     * - >=2.0, <=2.1, >1.0, <4.0
     * - =3.8.0 or exact match
     * - Combined: ">=2.0 <3.0"
     *
     * @param string $current Current version (e.g. `1.2.3`).
     * @param string $constraint One or more version constraint expression (e.g, `>=1.3`).
     * 
     * @return bool Return true if version satisfies, otherwise false.
     * @throws InvalidArgumentException If invalid constraints or current version is provided.
     * @example - Usage:
     * ```php
     * Version::satisfies('3.8.0', '3.8.0'); // true
     * Version::satisfies('3.8.1', '3.8.0'); // false
     * 
     * Version::satisfies('3.8.0', '^3.0'); // true
     * Version::satisfies('3.0.1', '^3.0'); // true
     * ```
     * @example - Combined Constraints:
     * ```php
     * Version::satisfies('3.8.0', '>=3.7 <4.0'); // true
     * Version::satisfies('3.6.9', '>=3.7 <4.0'); // false
     * Version::satisfies('4.0.0', '>=3.7 <4.0'); // false
     * ```
     */
    public static function satisfies(string $current, string $constraint): bool
    {
        $current = self::toVersion($current);
        $constraint = trim($constraint);

        $parts = self::$cache[$constraint]
            ??= self::toConstraints($constraint);

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (!self::isMatch($current, $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compare two versions using a specific operator.
     *
     * @param string $current The current version.
     * @param string $operator Comparison operator (e.g. >=, <, ==, !=).
     * @param string $target The target version.
     *
     * @return bool True if comparison is satisfied, otherwise false.
     * @throws InvalidArgumentException If invalid current or target version is provided.
     */
    public static function compare(string $current, string $operator, string $target): bool
    {
        $current = self::toVersion($current);
        $target = trim($target);

        self::assert($target);

        return self::isCompatible(
            $current, 
            $operator, 
            $target
        );
    }

    /**
     * Evaluate a single constraint expression.
     *
     * This method does not support multiple constraints.
     * Use {@see self::satisfies()} for combined expressions.
     *
     * @param string $current The current version.
     * @param string $constraint A single constraint (e.g. ">=1.3").
     *
     * @return bool True if version matches the constraint.
     * @throws InvalidArgumentException If multiple constraints are provided.
     * @throws InvalidArgumentException If invalid constraints or current version is provided.
     */
    public static function match(string $current, string $constraint): bool
    {
        $current = self::toVersion($current);
        $constraint = trim($constraint);

        if ($constraint === '' || preg_match('/\s/', $constraint)) {
            throw new InvalidArgumentException(
                ($constraint === '') 
                    ? 'Version constraint cannot be empty.'
                    : 'Multiple constraints not allowed in match(). Use satisfies().'
            );
        }

        return self::isMatch(
            $current, 
            $constraint
        );
    }

    /**
     * Evaluate a single constraint part against a version.
     *
     * @param string $current Normalized current version.
     * @param string $constraint Single constraint part.
     *
     * @return bool
     */
    private static function isMatch(string $current, string $constraint): bool
    {
        if (in_array($constraint[0], ['~', '^'], true)){
            self::assert($constraint);

            $base = substr($constraint, 1);
            $parts = explode('.', $base);

            $major = (int)($parts[0] ?? 0);
            $minor = (int)($parts[1] ?? 0);

            $lower = $base;

            if (count($parts) === 1) {
                $upper = ($major + 1) . '.0.0';
            } else {
                $upper = $major . '.' . ($minor + 1) . '.0';
            }

            return self::isCompatible($current, '>=', $lower)
                && self::isCompatible($current, '<', $upper);
        }

        if (preg_match('/^(>=|<=|>|<|==|=|!=|<>)\s*(.+)$/', $constraint, $m)) {
            return self::isCompatible($current, $m[1], $m[2]);
        }

        self::assert($constraint);
        return self::isCompatible($current, '==', $constraint);
    }

     /**
     * Assert constraint.
     *
     * Allowed:
     * - ^3.0
     * - >=1.2.3, <2.0, ==3.0 etc
     * - exact version: 1.2.3
     * 
     * @param string $value
     * @param bool $isConstraint
     * 
     * @return void
     */
    private static function assert(string $value, bool $isConstraint = true): void
    {
        if(!$isConstraint){
            if ($value === '' || !preg_match('/^' . self::VERSION . '$/', $value)) {
                throw new InvalidArgumentException(
                    ($value === '') 
                        ? 'Version cannot be empty.' 
                        : sprintf('Invalid version format: "%s". Expected format: x, x.y, or x.y.z', $value)
                );
            }

            return;
        }

        foreach (self::PATTERNS as $pattern) {
            if (preg_match("/^{$pattern}$/", $value)) {
                return;
            }
        }

        throw new InvalidArgumentException(
            sprintf('Invalid version constraint: "%s".', $value)
        );
    }

    /**
     * Perform version comparison using PHP version_compare.
     *
     * @param string $current Normalized current version.
     * @param string $operator Comparison operator.
     * @param string $target Target version.
     *
     * @return bool
     */
    private static function isCompatible(string $current, string $operator, string $target): bool
    {
        $result = version_compare($current, self::normalize($target));

        return match ($operator) {
            '>=' => $result >= 0,
            '<=' => $result <= 0,
            '>'  => $result > 0,
            '<'  => $result < 0,
            '!=', '<>' => $result !== 0,
            '=', '=='  => $result === 0,
            default => false,
        };
    }

    /**
     * Normalize constraints.
     *
     * @param string $constraint
     * 
     * @return array
     */
    private static function toConstraints(string $constraint): array
    {
        if ($constraint === '') {
            throw new InvalidArgumentException('Version constraint cannot be empty.');
        }

        return preg_split('/\s+/', $constraint) ?: [];
    }

    /**
     * Normalize current version.
     *
     * @param string $version
     * @return string
     */
    private static function toVersion(string $version): string 
    {
        $version = trim($version);

        if (str_starts_with($version, 'v')) {
            $version = substr($version, 1);
        }

        self::assert($version, false);

        return self::normalize($version);
    }

    /**
     * Normalize version into "x.y.z" format.
     *
     * Missing segments are filled with zero.
     *
     * Examples:
     * - "3"     → "3.0.0"
     * - "3.7"   → "3.7.0"
     * - "3.7.2" → "3.7.2"
     *
     * @param string $version Raw version string.
     *
     * @return string Normalized version.
     */
    private static function normalize(string $version): string
    {
        if (str_starts_with($version, 'v')) {
            $version = substr($version, 1);
        }

        $parts = explode('.', $version);

        return implode('.', [
            $parts[0] ?? '0',
            $parts[1] ?? '0',
            $parts[2] ?? '0',
        ]);
    }
}