<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Arrays;

class ArrayInput
{
    /**
     * @var array $parameters
    */
    private array $parameters = [];

    /**
     * @param array $parameters
    */
    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    /**
     * @param array $values
     * @param bool $default
     * 
     * @return mixed Return value of default value
    */
    public function getParameterOption(array $values, mixed $default = null): mixed
    {
        foreach ($values as $value) {
            if (isset($this->parameters[$value])) {
                return $this->parameters[$value];
            }
        }

        return $default;
    }

    /**
     * @param array $values
     * 
     * @return bool Return true if option exisit.
    */
    public function hasParameterOption(array $values): bool
    {
        foreach ($values as $value) {
            if (isset($this->parameters[$value])) {
                return true;
            }
        }

        return false;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getArguments(): array
    {
        $arguments = [];
        foreach ($this->parameters as $name => $value) {
            if (!is_numeric($name)) {
                $arguments[$name] = $value;
            }
        }

        return $arguments;
    }

    public function getOptions(): array
    {
        $options = [];
        foreach ($this->parameters as $name => $value) {
            if (is_numeric($name)) {
                $options[$name] = $value;
            }
        }

        return $options;
    }
}
