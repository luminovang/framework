<?php
/**
 * Luminova Framework weak reference object key class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Utils;

class WeakReference
{
    /**
     * Initializes a new WeakReference instance with an optional reference.
     *
     * @param mixed $reference The reference to store weakly, or null if no initial value is provided. 
     */
    public function __construct(private mixed $reference = null){}

    /**
     * Sets a new value for the reference.
     *
     * @param mixed $reference The new reference to store weakly.
     * 
     * @return self Returns the current WeakReference instance.
     */
    public function setValue(mixed $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    /**
     * Gets the value of the current reference.
     *
     * @return mixed Return the value stored in the reference, or null if it has been removed.
     */
    public function getValue(): mixed
    {
        return $this->reference;
    }
}