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
namespace Luminova\Interface;

use \JsonSerializable;

/**
 * Contract for objects that expose an explicit array representation.
 *
 * This interface defines a standard way to convert an object into
 * an array for data export, serialization, or transformation.
 * Conversion is always explicit—PHP does not invoke these methods
 * automatically.
 *
 * Implementations may reuse the same structure for `toArray()` and
 * `jsonSerialize()`, but the intent of each remains separate.
 *
 * @see https://wiki.php.net/rfc/to-array
 * @see https://www.php.net/manual/en/jsonserializable.jsonserialize.php
 */
interface Arrayable extends JsonSerializable
{
    /**
     * Return the object data as an array.
     *
     * This method is intended for explicit, developer-controlled
     * array conversion and is not a PHP magic method.
     *
     * @return array Returns the array representation of the object.
     */
    public function __toArray(): array;

    /**
     * Convert the object to an array.
     *
     * This is the primary and recommended method for retrieving
     * the array form of the object.
     *
     * @return array Returns the array representation of the object.
     */
    public function toArray(): array;

    /**
     * Specify data to be serialized to JSON.
     *
     * Used exclusively by `json_encode()` and does not imply
     * array casting or object iteration.
     *
     * @return mixed Returns the data to be serialized by `json_encode()`.
     */
    public function jsonSerialize(): mixed;
}