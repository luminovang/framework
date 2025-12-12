<?php
/**
 * Luminova Framework translation module.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Components\Languages;

use \Luminova\Interface\LazyObjectInterface;
use \Luminova\Exceptions\NotFoundException;
use \Luminova\Exceptions\RuntimeException;
use function \Luminova\Funcs\{root, locale, import};

final class Translator implements LazyObjectInterface
{
    /**
     * Translate constructor.
     *
     * @param string $locale The language locale for translations (e.g., 'en').
     */
    public function __construct(private ?string $locale = null) {}

    /**
     * Set language locale.
     *
     * @param string $locale The language locale for translations (e.g., 'en').
     * 
     * @return self Return instance of translator.
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Get the current locale.
     * 
     * @return string|null Return the current local, otherwise null.
     */
    public function getLocale(): ?string
    {
        return $this->locale ?: '';
    }

    /**
     * Get the translation for the given language key.
     *
     * @param string $lang The language transaction context and key(s) (e.g., `App.error`, `Users.login.invalid_email`).
     * @param string|null $default The fallback message to return if translation is not found.
     * @param array<string,string> $placeholders An option translation placeholders to replace contents of message with 
     *          (e.g, `['name' => 'Peter', '20']`).
     * 
     * @return string Return the translation text or the fallback value if any.
     * @throws NotFoundException When translation file cannot be loaded.
     * @throws RuntimeException If local is not valid.
     */
    public function get(string $lang, ?string $default = null, array $placeholders = []): string
    {
        $keys = explode('.', $lang);
        $filename = array_shift($keys);
        $translations = $this->load($filename);
        $default ??= '';

        if ($translations === []) {
            if(!$default){
                throw new NotFoundException(sprintf(
                    'No "%s" translations found for in: %s, set default message to suppress this error.', 
                    $lang, 
                    $filename
                ));
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

        return ($placeholders !== [] && $translations && is_string($translations))
            ? self::replacePlaceholders($translations, $placeholders)
            : $default;
    }

    /**
     * Load translations from language files.
     * @param string $filename 
     * @param bool $system for luminova internal system translations.
     * 
     * @return array Return an array of translations.
     * @throws RuntimeException If local is not valid.
     */
    private function load(string $filename, bool $system = false): array
    {
        $locale = $this->locale ?? locale();

        if(!$locale){
            throw new RuntimeException(
                'Invalid application locale. Set application locale either from "$lang->setLocale()" or in environment file "app.locale=xx".'
            );
        }

        $filename = "{$filename}.{$locale}.php";
        $path = $system 
            ? __DIR__ . DIRECTORY_SEPARATOR . 'Locals' . DIRECTORY_SEPARATOR 
            : root('/app/Languages/');

        return import($path . $filename, throw: false, once: true) ?? [];
    }

    /**
     * Translate placeholders.
     * 
     * @param string $message message to be translated.
     * @param array $placeholders The placeholders.
     * 
     * @return string Return the translated message.
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
