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

use \DOMText;
use \DOMNode;
use \DOMElement;
use \Normalizer;
use \DOMDocument;

final class Text
{
	/**
	 * Strict HTML block tags
	 *
	 * @var array $blockTags
	 */
	private static array $blockTags = [];

	/**
	 * Truncate a string to the specified length.
	 *
	 * If the string exceeds the specified length, it is truncated. When
	 * `$ellipsis` is enabled, an ellipsis (`...`) is appended and included
	 * within the specified length.
	 *
	 * @param string $text The text to truncate.
	 * @param int $length Maximum length of the resulting string.
	 * @param string $encoding Character encoding (default: `UTF-8`).
	 * @param bool $ellipsis Whether to append an ellipsis when truncating.
	 *
	 * @return string The truncated string.
	 */
	public static function truncate(
		string $text,
		int $length = 10,
		string $encoding = 'UTF-8',
		bool $ellipsis = false
	): string
	{
		if ($text === '' || $length <= 0) {
			return '';
		}

		if (mb_strlen($text, $encoding) <= $length) {
			return $text;
		}

		if ($ellipsis) {
			$length = max(0, $length - 3);
		}

		$text = mb_substr($text, 0, $length, $encoding);

		return $ellipsis ? $text . '...' : $text;
	}

	/**
	 * Returns the portion of a message before the first occurrence of a keyword.
	 *
	 * The keyword search is case-insensitive. If the keyword is found, all content
	 * starting from the keyword position is removed. If the keyword is not found,
	 * the original message is returned unchanged.
	 *
	 * This helper can be used to extract text before a specific marker or delimiter
	 * within a string.
	 *
	 * @param string $message Input message to process.
	 * @param string $needle Keyword or marker used as the cut-off point.
	 *
	 * @return string The content before the keyword, or the original message if not found.
	 */
	public static function before(string $message, string $needle): string
    {
		$message = trim($message);
		$pos = self::indexOf($message, $needle);

        if ($pos === null) {
            return $message;
        }

        return trim(substr($message, 0, $pos));
    }

	/**
	 * Returns the portion of a message after the first occurrence of a keyword.
	 *
	 * The keyword search is case-insensitive. If the keyword is found, all content
	 * after the keyword is returned. If the keyword is not found, the original
	 * message is returned unchanged.
	 *
	 * This helper can be used to extract text following a specific marker or
	 * delimiter within a string.
	 *
	 * @param string $message Input message to process.
	 * @param string $needle Keyword or marker used as the starting point.
	 *
	 * @return string The content after the keyword, or the original message if not found.
	 */
	public static function after(string $message, string $needle): string
	{
		$message = trim($message);
		$pos = self::indexOf($message, $needle);

		if ($pos === null) {
			return $message;
		}

		return trim(substr($message, $pos + strlen($needle)));
	}

	/**
	 * Normalize text for display purposes.
	 *
	 * Applies common cleanup rules for user-generated content.
	 *
	 * @param string $text The text to process.
	 * @param bool $stripTags Whether to remove HTML tags before normalization.
	 * @param bool $strictWhitespace Whether to normalize only spaces and non-breaking spaces.
	 *
	 * @return string Return normalized text.
	 * 
	 * @see self::stripTags() Advanced HTML stripping control.
	 */
	public static function normalize(
		string $text,
		bool $stripTags = false,
		bool $strictWhitespace = true
	): string 
	{
		$text = self::normalizeHtmlNewlines($text);

		if ($stripTags) {
			$text = self::stripTags($text);
		}

		$text = self::normalizeUnicode($text);
		$text = self::normalizeInvisible($text);
		$text = self::normalizeNewlines($text);
		$text = self::normalizeWhitespace($text, $strictWhitespace);

		return trim($text);
	}

	/**
	 * Normalize whitespace characters.
	 *
	 * Converts repeated whitespace characters into a single space.
	 * Newline characters are preserved.
	 *
	 * @param string $text The text to process.
	 * @param bool $strict When true, only spaces and non-breaking spaces are normalized.
	 *
	 * @return string Return normalized text.
	 */
	public static function normalizeWhitespace(string $text, bool $strict = false): string
	{
		$pattern = $strict
			? '/[ \x{00A0}]{2,}/u'
			: '/[^\S\r\n]{2,}/u';

		return preg_replace($pattern, ' ', $text) ?? $text;
	}

	/**
	 * Normalize line endings and excessive blank lines.
	 *
	 * Converts HTML breaks and line endings to LF and limits consecutive blank lines.
	 *
	 * @param string $text The text to process.
	 *
	 * @return string Return normalized text.
	 */
	public static function normalizeNewlines(string $text): string
	{
		$text = self::normalizeHtmlNewlines($text);

		// Normalize all line endings to LF
		$text = preg_replace('/\R/u', "\n", $text) ?? $text;

		// Remove trailing whitespace from each line
		$text = preg_replace('/[ \x{00A0}\t]+$/mu', '', $text) ?? $text;

		// Limit consecutive blank lines
		return preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
	}

	/**
	 * Convert HTML line breaks to newline characters.
	 *
	 * @param string $text The text to process.
	 *
	 * @return string Return normalized text.
	 */
	public static function normalizeHtmlNewlines(string $text): string
	{
		return preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
	}

	/**
	 * Normalize Unicode characters.
	 *
	 * Converts Unicode characters into a consistent canonical form.
	 *
	 * @param string $text The text to process.
	 * 
	 * @return string Return normalized text.
	 */
	public static function normalizeUnicode(string $text): string
	{
		if (function_exists('normalizer_normalize')) {
			return normalizer_normalize($text, Normalizer::FORM_C) ?: $text;
		}

		return $text;
	}

	/**
	 * Remove invisible and control characters.
	 *
	 * Preserves:
	 * - Line feed (`\n`)
	 * - Carriage return (`\r`)
	 * - Horizontal tab (`\t`)
	 *
	 * @param string $text The text to process.
	 *
	 * @return string
	 */
	public static function normalizeInvisible(string $text): string
	{
		return preg_replace(
			'/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\p{Cf}]/u',
			'',
			$text
		) ?? $text;
	}

	/**
	 * Strip HTML tags from text content.
	 * 
	 * This removes HTML tags while optionally retaining specific tags or replacing
	 * removed elements with custom text.
	 *
	 * By default, tags are removed while preserving their inner content.
	 * When strict mode is enabled, block tags such as script/style elements
	 * are removed together with their contents.
	 *
	 * @param string $text Text or HTML content.
	 * @param bool $strict Whether to remove block elements with their contents (default: false).
	 * @param string[]|string|null $retain Tags to preserve (e.g. ['b', 'i']) or HTML string '<b><i>'.
	 * @param (callable(string $tag):string)|string $replacement Replacement text or callback
	 *                                               receiving the removed tag name.
	 *
	 * @return string Return a cleaned text content.
	 */
	public static function stripTags(
		string $text,
		bool $strict = false,
		array|string|null $retain = null,
		string|callable $replacement = ''
	): string 
	{
		if ($text === '') {
			return '';
		}

		$retain = self::flipTagRetainers($retain ?? []);
		$dom = new DOMDocument();

		libxml_use_internal_errors(true);

		if (!$dom->loadHTML(
			'<?xml encoding="utf-8" ?>' . $text,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		)) {
			libxml_clear_errors();
			return $text;
		}

		libxml_clear_errors();

		$walker = static function (DOMNode $node) use (
			&$walker,
			$dom,
			$retain,
			$replacement,
			$strict
		): void {
			if (!$node->hasChildNodes()) {
				return;
			}

			/** @var DOMNode[] $children */
			$children = iterator_to_array($node->childNodes);

			foreach ($children as $child) {
				if (!$child instanceof DOMElement) {
					continue;
				}

				$tag = strtolower($child->tagName);

				if (isset($retain[$tag])) {
					$walker($child);
					continue;
				}

				$parent = $child->parentNode;

				if (!$parent) {
					continue;
				}

				$replacementNode = is_callable($replacement)
					? $replacement($tag)
					: $replacement;

				$replacementNode = ($replacementNode !== '')
					? $dom->createTextNode((string) $replacementNode)
					: null;

				if ($strict && self::isBlockTag($tag)) {
					self::removeHtmlElement($parent, $child, $replacementNode);
					continue;
				}

				$walker($child);

				self::unwrapHtmlElement($parent, $child, $replacementNode);
			}
		};

		$walker($dom);

		return trim($dom->saveHTML());
	}

	/**
	 * Finds the position of the first occurrence of a substring.
	 *
	 * The search is case-insensitive. Returns the zero-based position of the
	 * substring if found, or null when the substring does not exist.
	 *
	 * @param string $message Input string to search.
	 * @param string $needle Substring to locate.
	 *
	 * @return int|null The substring position, or null if not found.
	 */
	private static function indexOf(string $message, string $needle): ?int
	{
		if ($message === '' || $needle === '') {
			return null;
		}

		$pos = stripos($message, $needle);

		if ($pos === false) {
			return null;
		}

		return $pos;
	}

	/**
	 * Normalize and flip retainers.
	 *
	 * @param array|string $retain
	 * 
	 * @return array
	 */
	private static function flipTagRetainers(array|string $retain): array
	{
		if ($retain === [] || $retain === '') {
			return [];
		}

		if (is_string($retain)) {
			preg_match_all('/<([a-z][a-z0-9]*)>/i', $retain, $matches);
			$retain = $matches[1];
		}

		$retains = [];

		foreach ($retain as $tag) {
			$tag = strtolower(trim($tag, " \t\n\r\0\x0B<>/"));

			if ($tag !== '') {
				$retains[$tag] = true;
			}
		}

		return $retains;
	}

	/**
	 * Check if tag matches any of defined block tags. 
	 *
	 * @param string $tag The tag name.
	 * 
	 * @return bool Return true if match, otherwise false.
	 */
	private static function isBlockTag(string $tag): bool
	{
		if (self::$blockTags === []) {
			self::$blockTags = array_fill_keys([
				'script', 'style', 'iframe', 'object', 'embed', 'applet',
				'form', 'input', 'button', 'textarea', 'select', 'a',
				'label', 'fieldset', 'legend', 'datalist', 'meta',
				'link', 'base', 'keygen', 'output', 'body', 'html',
				'template', 'pre', 'code',
			], true);
		}

		return isset(self::$blockTags[$tag]);
	}

	/**
	 * Remove an HTML element and all its children.
	 *
	 * @param DOMNode $parent
	 * @param DOMElement $element
	 * @param DOMText|null $replacement
	 *
	 * @return void
	 */
	private static function removeHtmlElement(
		DOMNode $parent,
		DOMElement $element,
		?DOMText $replacement = null
	): void 
	{
		if ($replacement) {
			$parent->replaceChild($replacement, $element);
			return;
		}

		$parent->removeChild($element);
	}

	/**
	 * Remove an HTML element while preserving its children.
	 *
	 * @param DOMNode $parent
	 * @param DOMElement $element
	 * @param DOMText|null $replacement
	 *
	 * @return void
	 */
	private static function unwrapHtmlElement(
		DOMNode $parent,
		DOMElement $element,
		?DOMText $replacement = null
	): void 
	{
		if ($replacement) {
			$parent->insertBefore($replacement, $element);
		}

		while ($element->firstChild) {
			$parent->insertBefore($element->firstChild, $element);
		}

		$parent->removeChild($element);
	}
}