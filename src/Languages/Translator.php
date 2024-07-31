<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Languages;

use \Luminova\Exceptions\NotFoundException;

final class Translator
{
    /**
     * The current language/locale to work with.
     *
     * @var string|null
     */
    private string|null $locale = null;

    /**
     * Translate constructor.
     *
     * @param string $locale The language code (e.g., 'en') for translations.
     */
    public function __construct(?string $locale = null)
    {
        $locale ??= locale();
        
        if( $locale ){
            $this->locale = $locale;
        }
    }

    /**
     * Set locale 
     *
     * @param string $locale The locale
     * 
     * @return $this
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Get locale
     * 
     * @return string $this->locale
    */
    public function getLocale(): string
    {
        return $this->locale ?? locale();
    }

    /**
     * Load translations from language files.
     * @param string $filename 
     * @param bool $system for system translations
     * 
     * @return array An array of translations.
     */
    private function load(string $filename, bool $system = false): array
    {
        $translation = [];
        if($system){
            $path = __DIR__ . DIRECTORY_SEPARATOR;
            $path .= 'Locals' . DIRECTORY_SEPARATOR;
        }else{
            $path = path('languages');
        }

        $path .= "{$filename}.{$this->locale}.php";

        if (file_exists($path)) {
            $translation = include_once $path;
        }

        return $translation;
    }

    /**
     * Get the translation for the given language key.
     *
     * @param string $lang The language key (e.g., 'filename.key1.key2').
     * @param string $default The fallback value to return if translation is not found.
     * @param array $placeholders placeholders
     * 
     * @return string The translation text or the fallback value if any.
     * @throws NotFoundException When translation file cannot be loaded.
     */
    public function get(string $lang, string $default = '', array $placeholders = []): string
    {
        $keys = explode('.', $lang);
        $filename = array_shift($keys);
        $translations = $this->load($filename);

        if ($translations === []) {
            if($default === ''){
                throw new NotFoundException("No translations found for file: {$filename}");
            }

            return $default;
        }

        foreach ($keys as $key) {
            if (isset($translations[$key])) {
                $translations = $translations[$key];
            } else {
                return $default;
            }
        }

        if($translations !== '' && $placeholders !== []){
            $translations = self::replacePlaceholders($translations, $placeholders);
        }

        return $translations ?? $default;
    }

    /**
     * Translate placeholders.
     * 
     * @param string $message message to be translated.
     * @param array $placeholders array.
     * 
     * @return string 
    */
    private static function replacePlaceholders(string $message, array $placeholders): string 
    {
        if (array_is_list($placeholders)) {
            return vsprintf($message, $placeholders);
        }

        $array = [];
        foreach ($placeholders as $key => $value) {
            $array['{' . $key . '}'] = $value;
        }

        return strtr($message, $array);
    }
}
