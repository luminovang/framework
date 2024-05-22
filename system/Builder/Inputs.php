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

class Inputs
{
    private array $inputs = [];

    public function text(string $type, array $attributes = null): void 
    {
        $this->inputs['text'][] = [
            'type' => $type,
            'attr' => $attributes
        ];
    }

    public function textarea(mixed $value = null, array $attributes = null): void 
    {
        $this->inputs['textarea'][] = [
            'type' => 'textarea',
            'value' => $value,
            'attr' => $attributes
        ];
    }

    public function select(array $options, mixed $selected = null, bool $multiple = false, array $attributes = null): void 
    {
        $this->inputs['select'][] = [
            'type' => 'select',
            'selected' => $selected,
            'multiple' => $multiple,
            'options' => $options,
            'attr' => $attributes
        ];
    }

    public function get(): array 
    {
        return $this->inputs;
    }
}