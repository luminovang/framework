<?php 
/**
 * Luminova Framework Smarty/Twig Proxy
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Template\Engines;

use \ArrayAccess;
use \Luminova\Exceptions\BadMethodCallException;

final class Proxy implements ArrayAccess
{
    /** 
     * Internal key to hold the original view object 
     */
    public const VIEW_KEY = '__LUMINOVA_TEMPLATE_VIEW_OBJECT__';

    /**
     * Initialize a template proxy.
     * 
     * This proxy provides a unified way to access template variables and the underlying view object.
     * It supports property access (`$proxy->key`), method calls (`$proxy->method()`), 
     * and optional array access for Smarty templates.
     * 
     * @param object $scope The main view object the proxy wraps.
     * @param array<string,mixed> $options Template variables accessible via the proxy.
     * @param bool $isArrayAccess Whether to allow array-style access (e.g., $proxy['key']). 
     *                  Required for Smarty templates; set false if not needed.
     */
    public function __construct(
        private object $scope, 
        private array $options = [], 
        private bool $isArrayAccess = true
    )
    {}

    /**
     * Access property dynamically
     */
    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->options)) {
            return $this->resolve($this->options[$name]);
        }

        if (isset($this->scope->{$name})) {
            return $this->resolve($this->scope->{$name});
        }

        return null;
    }

    /**
     * Call methods on view object
     */
    public function __call(string $name, array $args): mixed
    {
        if (!method_exists($this->scope, $name)) {
            if (!$this->arrayAccess && array_key_exists($name, $this->options)) {
                return $this->__get($name);
            }

            throw new BadMethodCallException(
                sprintf("Method '%s' does not exist on view object.", $name)
            );
        }

        return $this->resolve($this->scope->{$name}(...$args));
    }

    /**
     * Wrap Proxy for consistent deep access.
     * 
     * This ensure object members can be accessed without smarty assign=
     * 
     * @param mixed $value Value to resolve.
     * 
     * @return mixed Return resolved result value.
     */
    private function resolve(mixed $value): mixed
    {
        if (is_object($value)) {
            return new self($value, [], $this->isArrayAccess);
        }

        // if (is_array($value)) {
        //   return new self($this->scope, $value, $this->isArrayAccess);
        // }

        return $value;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->isArrayAccess && 
            (isset($this->scope->{$offset}) || isset($this->options[$offset]));
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->isArrayAccess ? $this->__get((string) $offset) : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void {}
    public function offsetUnset(mixed $offset): void {}
}