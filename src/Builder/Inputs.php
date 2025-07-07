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
namespace Luminova\Builder;

use \Luminova\Builder\Document;

/**
 * Available input types handled dynamically
 * 
 * 
 * @method static string text(string $name = '', string $value = '', bool $closeElement = false, array $attr = []) Generate a text input field
 * @method static string email(string $name = '', string $value = '', bool $closeElement = false, array $attr = []) Generate an email input field
 * @method static string url(string $name = '', string $value = '', bool $closeElement = false, array $attr = []) Generate a URL input field
 * @method static string number(string $name = '', string $value = '', bool $closeElement = false, array $attr = []) Generate a number input field
 * @method static string range(string $name = '', string $value = '', bool $closeElement = false, array $attr = []) Generate a range input field
 * @method static string datetime(string $name = '', string $value = '', bool $closeElement = false, array $attr = []) Generate a datetime input field using 'datetime-local'
 * @method static string date(string $name = '', string $value = '', bool $closeElement = false, array $attr = []) Generate a date input field
 * @method static string time(string $name = '', string $value = '', bool $closeElement = false, array $attr = []) Generate a time input field
 * @method static string color(string $name = '', string $value = '', bool $closeElement = false, array $attr = []) Generate a color input field
 * @method static string hidden(string $name = '', bool $closeElement = false, string $value = '', array $attr = []) Generate a hidden input field
 * @method static string tel(string $name = '', bool $closeElement = false, string $value = '', array $attr = []) Generate a telephone number input field
 * @method static string password(string $name = '', bool $closeElement = false, string $value = '', array $attr = []) Generate a password input field
 */
class Inputs extends Document
{
    /**
     * Dynamically generates an HTML input element based on the called static method name.
     *
     * @param string $method The input type (method name being called).
     * @param array $arguments The arguments: [string $name, mixed $value, bool $close, array $attr].
     *                          - $name (string): The name attribute of the input.
     *                          - $value (mixed): The value attribute of the input (optional).
     *                          - $close (bool): Whether the input is self-closing (default: false).
     *                          - $attr (array): Additional attributes for the input element (optional).
     * 
     * @return string Return the generated HTML input element.
     * 
     *  **Supported types:**
     * - inputs: Handles various input types by delegating to the input generation method.
     */
    public static function __callStatic(string $method, array $arguments): string
    {
        $method = str_replace('_', '-', $method);
        [$name, $value, $close, $attr] = $arguments + [null, null, false, []];
        
        return self::input($method, $name, $value, $close, $attr);
    }

    /**
     * Generate an HTML element (input or button) with the specified attributes.
     *
     * @param string $tag The HTML tag to create (e.g., 'input', 'button').
     * @param string|null $type Optional input type (e.g., 'text', 'password', 'submit').
     * @param string|null $name Optional name attribute of the element.
     * @param string|null $value Optional value for the input or the text content for the button.
     * @param bool $closeElement Whether to close the tag with content (e.g., `<button>`) or self-close (default: false).
     * @param array<string,mixed> $attributes Additional HTML attributes (e.g., 'class', 'id', 'data-*').
     * 
     * @return string Return the generated HTML form input tag string.
     */
    public static function element(
        string $tag,
        ?string $type = null, 
        ?string $name = null, 
        ?string $value = null, 
        bool $closeElement = false,
        array $attributes = []
    ): string {
        $tag = self::$xhtmlStrictTagNames 
            ? strtolower(parent::esc($tag)) 
            : parent::esc($tag);
   
        if($type){
            $type = strtolower(trim($type));
            $type = (self::$invalidInputTypes[$type] ?? parent::esc($type));
        }

        if($closeElement){
            return sprintf(
                '<%s%s%s%s>%s</%s>',
                $tag,
                ($type ? ' type="' . $type . '"' : ''),
                ($name ? ' name="' . parent::esc($name) . '"' : ''),
                parent::attributes($attributes),
                ($value ? parent::esc($value) : ''),
                $tag
            );
        }

        return sprintf(
            '<%s%s%s%s%s />',
            $tag,
            ($type ? ' type="' . $type . '"' : ''),
            ($name ? ' name="' . parent::esc($name) . '"' : ''),
            ($value ? ' value="' . parent::esc($value) . '"' : ''),
            parent::attributes($attributes)
        );
    }

    /**
     * Generates multiple HTML form inputs elements based on an array of element specifications.
     *
     * Each element in the array should be an associative array containing the following keys:
     * 
     * - **tag** (string): The HTML tag to generate (e.g., 'input', 'button').
     * - **type** (string): Optional type attribute for elements like 'input' (default: empty string).
     * - **name** (string): Optional name attribute for the element (default: empty string).
     * - **value** (string): Optional value to be placed inside the element, or for 'input' elements (default: empty string).
     * - **closeElement** (bool): Whether to close the tag with content or self-close it (default: false).
     * - **attributes** (array): Optional associative array of HTML attributes for the element (default: empty array).
     *
     * @param array $inputs An array of associative arrays where each represents an HTML element and its attributes.
     * 
     * @return string Returns the generated HTML input elements as a concatenated string.
     *
     * Example usage:
     * ```php
     * $inputs = [
     *     [
     *         'tag' => 'input',
     *         'type' => 'text',
     *         'name' => 'username',
     *         'value' => 'JohnDoe',
     *         'closeElement' => false,
     *         'attributes' => ['class' => 'form-control']
     *     ],
     *     [
     *         'tag' => 'input',
     *         'type' => 'password',
     *         'name' => 'password',
     *         'attributes' => ['class' => 'form-control']
     *     ]
     * ];
     *
     * echo Inputs::elements($inputs);
     * ```
     */
    public static function elements(array $inputs): string
    {
        $elements = '';

        foreach ($inputs as $input) {
            $elements .= self::element(
                $input['tag'], 
                $input['type'] ?? null, 
                $input['name'] ?? null, 
                $input['value'] ?? null, 
                $input['closeElement'] ?? false,
                $input['attributes'] ?? []
            ) . PHP_EOL;
        }

        return $elements;
    }

    /**
     * Generate an HTML form.
     *
     * @param string|array<int,array> $inputs The form inputs (pre-generated) or an array of inputs.
     * @param string $action The form action URL (default: '').
     * @param string $method The form submission method (e.g, 'POST', 'GET').
     * @param array<string,mixed> $attributes Additional HTML attributes for the form tag.
     * 
     * @return string Return the generated form HTML.
     */
    public static function form(
        string|array $inputs,
        string $action = '', 
        string $method = 'GET', 
        array $attributes = []
    ): string {
        $inputs = is_array($inputs) 
            ? self::elements($inputs) 
            : $inputs;

        return sprintf(
            '<form method="%s" action="%s"%s>%s</form>',
            parent::esc($method),
            parent::esc($action),
            parent::attributes($attributes),
            $inputs
        );
    }

    /**
     * Generate an HTML input field.
     *
     * @param string $type The type of input (e.g., 'text', 'password', 'email').
     * @param string $name The name of the input.
     * @param string $value The value of the input field.
     * @param array<string,string> $attributes Additional HTML attributes (e.g, `classNames`, `id's`, `data-attributes`).
     * 
     * @return string Return the generated input HTML.
     */
    public static function input(
        string $type, 
        string $name, 
        string $value = '', 
        array $attributes = []
    ): string {
        return self::element('input', $type, $name, $value, false, $attributes);
    }

    /**
     * Generates an HTML file input element for uploading files, with optional capture settings.
     *
     * @param string $name The name attribute for the input element.
     * @param string|null $capture Specifies the camera to be used for capturing files (default: null):
     *        - 'front': Use the front camera (user-facing).
     *        - 'back': Use the back camera (environment-facing).
     *        - 'switch': Allow the user to choose which camera to use.
     *        - null: No specific camera preference.
     * @param array $attributes Optional additional attributes for the input element.
     *
     * @return string Returns the generated HTML file input element as a string.
     */
    public static function file(
        string $name, 
        ?string $capture = null, 
        array $attributes = []
    ): string {

        if($capture){
            $attributes['accept'] = $attributes['accept'] ?? 'image/*, video/*';
            $attributes['capture'] = $attributes['capture'] ?? match($capture){
                'front' => 'user',
                'back' => 'environment',
                'switch' => null,
                default => $capture
            };
        }

        return self::element('input', 'file', $name, null, false, $attributes);
    }

    /**
     * Generates an HTML label element with associated attributes.
     *
     * @param string $for The ID of the input element this label is for.
     * @param string $text The text displayed inside the label.
     * @param array $attributes Optional additional HTML attributes (e.g., 'class', 'style').
     *
     * @return string Return the generated HTML label element.
     */
    public static function label(
        string $for, 
        string $text,
        array $attributes = []
    ): string {
        return sprintf(
            '<label for="%s"%s>%s</label>',
            parent::esc($for),
            parent::attributes($attributes),
            parent::esc($text)
        );
    }

    /**
     * Generate a button element.
     *
     * @param string $type The type of the button (e.g., 'submit', 'button').
     * @param string $name The name attribute of the button.
     * @param string $text The button text.
     * @param array<string,string> $attributes Additional HTML attributes (e.g, `classNames`, `id's`, `data-attributes`).
     * 
     * @return string Return the generated button HTML.
     */
    public static function button(
        string $type, 
        string $name = '', 
        string $text = 'Submit', 
        array $attributes = []
    ): string {
        return self::element('button', $type, $name, $text, true, $attributes);
    }

    /**
     * Generate a textarea field.
     *
     * @param string $name The name of the textarea.
     * @param string $value The value inside the textarea.
     * @param array<string,string> $attributes Additional HTML attributes (e.g, `classNames`, `id's`, `data-attributes`).
     * 
     * @return string Return the generated textarea HTML.
     */
    public static function textarea(string $name, string $value = '', array $attributes = []): string
    {
        return sprintf(
            '<textarea name="%s"%s>%s</textarea>',
            parent::esc($name),
            parent::attributes($attributes),
            parent::esc($value)
        );
    }

    /**
     * Generate a checkbox input field.
     *
     * @param string $name The name of the input.
     * @param string $value The value of the checkbox.
     * @param bool $checked Whether input is checked (default: false).
     * @param array<string,string> $attributes Additional HTML attributes (e.g, `classNames`, `id's`, `data-attributes`).
     * 
     * @return string Return the generated checkbox input HTML.
     */
    public static function checkbox(
        string $name, 
        string $value = '', 
        bool $checked = false, 
        array $attributes = []
    ): string
    {
        if($checked){
            $attributes['checked'] = 'checked';
        }
        
        return self::input('checkbox', $name, $value, $attributes);
    }

    /**
     * Generate a radio input field.
     *
     * @param string $name The name of the input.
     * @param string $value The value of the radio input.
     * @param bool $checked Whether input is checked (default: false).
     * @param array<string,string> $attributes Additional HTML attributes (e.g, `classNames`, `id's`, `data-attributes`).
     * 
     * @return string Return the generated checkbox input HTML.
     */
    public static function radio(
        string $name, 
        string $value = '', 
        bool $checked = false, 
        array $attributes = []
    ): string
    {
        if($checked){
            $attributes['checked'] = 'checked';
        }
        
        return self::input('radio', $name, $value, $attributes);
    }

    /**
     * Generate a single HTML `<option>` element.
     *
     * @param string|int $key The key-value attribute of the option element.
     * @param string|null $value The display text for the option element or null for self-closing (default: null).
     * @param bool $selected Whether the option should be marked as selected (default: false).
     * @param bool $disabled Whether the option should be marked as disabled (default: false).
     * 
     * @return string Return the generated HTML `<option>` element with proper escaping.
     */
    public static function option(
        string|int $key, 
        ?string $value = null, 
        bool $selected = false, 
        bool $disabled =  false
    ): string
    {
        $key = (string) $key;
        return sprintf(
            '<option value="%s"%s%s%s',
            parent::esc($key),
            $selected ? ' selected="selected"' : '',
            $disabled ? ' disabled="disabled"' : '',
            ($value === null) ? ' />' : '>' . parent::esc($value) . '</option>'
        );
    }

    /**
     * Generate an HTML `<optgroup>` element with options.
     *
     * @param array<string|int,string>|string $options An HTML of options or Array options as key-value pairs, 
     *                                          where the key is the option value and the value is the display text.
     * @param bool $indexedKey If true, use the original keys; if false, use values as keys on indexed option keys.
     * @param string|array $selected The selected value(s). Can be a string for single select or an array for multiple select.
     * @param string|array $disabled The disabled value(s). Can be a string for single select or an array for multiple select.
     * @param array<string,string> $attributes Additional HTML attributes for the `<optgroup>` element.
     * 
     * @return string Return the generated HTML `<optgroup>` element containing the option elements.
     */
    public static function optgroup(
        array|string $options, 
        bool $indexedKey = true, 
        string|array $selected = '', 
        string|array $disabled = '',
        array $attributes = []
    ): string {
        $optgroup = '<optgroup' . parent::attributes($attributes) . '>';

        if(is_string($options)){
            $optgroup .= $options;
        }else{
            foreach ($options as $key => $value) {
                $key = (!$indexedKey && !is_string($key)) ? $value : $key;
                $isSelected = ($key == $selected || (is_array($selected) && in_array($key, $selected)));
                $isDisabled = ($key == $disabled || (is_array($disabled) && in_array($key, $disabled)));

                $optgroup .= self::option($key, $value, $isSelected, $isDisabled) . PHP_EOL;
            }
        }

        $optgroup .= '</optgroup>';

        return $optgroup;
    }

    /**
     * Generate an HTML <select> dropdown element with optional optgroup support.
     *
     * @param string $name The name attribute of the <select> element.
     * @param array<string|int,string|array<string|int,string>>|string $options An HTML string of options or an array of options, 
     *                          where the key is the option value and the value is the display text, or an array with optgroup entries (e.g., `[['foo' => 'Foo', 'bar' => 'Bar'], ...]`).
     * @param array<int,string|int|float>|string $selected The selected value(s). Can be a string for single select or an array for multiple select.
     * @param array<int,string|int|float>|string $disabled The disabled value(s). Can be a string for single select or an array for multiple select.
     * @param array<string,string> $attributes Additional HTML attributes for the `<select>` element.
     * @param bool $indexedKey Whether to retain the option array index key as option value or use the text as the value (default: true).
     *
     * @return string The generated HTML string for the <select> dropdown.
     */
    public static function select(
        string $name, 
        array|string $options, 
        array|string $selected = '', 
        array|string $disabled = '', 
        array $attributes = [],
        bool $indexedKey = true
    ): string
    {
        $attributes['id'] = $attributes['id'] ?? $name;
        $select = '<select name="' . parent::esc($name) . '"' . parent::attributes($attributes) . '>' . PHP_EOL;

        if(is_string($options)){
            $select .= $options;
        }else{
            foreach ($options as $key => $val) {
                if (is_array($val)) {
                    $select .= self::optgroup($val, $indexedKey, $selected, $disabled, [
                        'label' => $key
                    ]) . PHP_EOL;
                } else {
                    $key = (!$indexedKey && !is_string($key)) ? $val : $key;
                    $isSelected = ($key == $selected || (is_array($selected) && in_array($key, $selected)));
                    $isDisabled = ($key == $disabled || (is_array($disabled) && in_array($key, $disabled)));

                    $select .= self::option($key, $val, $isSelected, $isDisabled) . PHP_EOL;
                }
            }
        }

        $select .= '</select>';
        return $select;
    }

    /**
     * Generate an HTML <datalist> dropdown element with searchable input support.
     *
     * @param string $name The name attribute of the <input> element.
     * @param array<int,string>|string $options An HTML string of options or an array of options values.
     * @param array<string,string> $attributes Additional HTML attributes for the `<datalist>` element.
     *
     * @return string The generated HTML string for the <datalist> dropdown.
     * @see https://www.w3schools.com/html/tryit.asp?filename=tryhtml_elem_datalist
     */
    public static function datalist(
        string $name, 
        array|string $options, 
        array $attributes = [],
    ): string
    {
        $attributes['id'] = $attributes['id'] ?? $name;
        $select = '<input type="search" list="' . $attributes['id'] . '" name="' . $name . '">' . PHP_EOL;
        $select .= '<datalist' . parent::attributes($attributes) . '>' . PHP_EOL;

        if(is_string($options)){
            $select .= $options;
        }else{
            foreach ($options as $value) {
                $select .= self::option($value, null) . PHP_EOL;
            }
        }

        $select .= '</datalist>';
        return $select;
    }
}