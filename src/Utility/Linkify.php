<?php 
/**
 * Luminova Framework.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utility;

use \Closure;
use Luminova\Utility\Text;

/**
 * Each link type can be customized with its own base URL,
 * matching patterns, and HTML attributes.
 *
 * Options:
 *
 * - `attributes`: HTML attributes added to generated links.
 * - `base`: Redirect/base URL (not applied to emails and phones).
 * - `mode`: Processing mode:
 *   - `Linkify::LINK`  Converts matches into links.
 *   - `Linkify::STRIP` Removes matches from text.
 * - `filter`: Custom matching rules.
 *   - `patterns`: Custom patterns to match.
 *   - `dropUnmatched`: Remove unmatched values for this type.
 *
 * @phpstan-type Modes = 'link'|'strip'
 * @phpstan-type Types = 'urls'|'emails'|'phones'|'maps'|'hashtags'|'mentions'
 * @phpstan-type Option array{
 *     base?: ?string,
 *     mode?: value-of<Modes>,
 *     attributes?: array<string,string>,
 *     filter?: array{
 *         patterns?: string[],
 *         dropUnmatched?: bool
 *     }
 * }
 * @phpstan-type AllOptions array{
 *     urls?: Option,
 *     emails?: Option,
 *     mentions?: Option,
 *     phones?: Option,
 *     hashtags?: Option,
 *     maps?: Option
 * }
 * @phpstan-type Options = Option|AllOptions
 */
final class Linkify
{
	/**
	 * Link processing mode.
	 *
	 * Convert detected URLs into formatted links (e.g., HTML or Markdown).
	 */
	public const MODE_LINK  = 'link';

	/**
	 * Remove all detected URLs from the text.
	 * 
	 * Remove all detected URLs from the text.
	 */
	public const MODE_STRIP = 'strip';

	/**
	 * Format detected links as Markdown link.
	 * 
	 * @var string FORMAT_MARKDOWN
	 */
	public const FORMAT_MARKDOWN = 'markdown';

	/**
	 * Format detected links as HTML clickable link.
	 * 
	 * @var string FORMAT_HTML
	 */
	public const FORMAT_HTML = 'html';

	/**
	 * Link type: URLS.
	 * 
	 * @var string URLS
	 */
	public const URLS = 'urls';

	/**
	 * Link type: Email addresses.
	 * 
	 * @var string MENTIONS
	 */
	public const EMAILS = 'emails';

	/**
	 * Link type: User mentions.
	 */
	public const MENTIONS = 'mentions';

	/**
	 * Link type: Phone numbers.
	 * 
	 * @var string PHONES
	 */
	public const PHONES = 'phones';

	/**
	 * Link type: Hashtags (@user).
	 * 
	 * @var string HASHTAGS 
	 */
	public const HASHTAGS = 'hashtags';

	/**
	 * Link type: Map locations.
	 * 
	 * @var string MAPS
	 */
	public const MAPS = 'maps';

	/**
	 * Supported link detection types.
	 *
	 * @var string[] OPTIONS
	 */
	public const OPTIONS = [
		self::URLS,
		self::EMAILS,
		self::MENTIONS,
		self::PHONES,
		self::HASHTAGS,
		self::MAPS,
	];

	/**
	 * Converts supported text patterns into HTML or Markdown links.
	 *
	 * Supported patterns:
	 * - URLs (`urls`)
	 * - Email addresses (`emails`)
	 * - Phone numbers (`phones`)
	 * - User mentions (`mentions`)
	 * - Hashtags (`hashtags`)
	 * - Map locations (`maps`)
	 *
	 * Each type can be configured independently with custom base URLs,
	 * matching filters, and HTML attributes.
	 *
	 * @param string $text Text to process.
	 * @param string[]|Types[] $types Link types to process (default: `all`). 
	 * 			- Empty array enables all types.
	 * @param array{
	 *      base?: ?string,
     * 		mode?: 'link'|'strip',
     * 		attributes?: array<string,string>,
     * 		filter?: array{
     * 			patterns: string[],
     * 			dropUnmatched?: bool 
     * 		}
	 * }|Options $options Global or per-type configuration.
	 * @param string $format Output format (`Linkify::FORMAT_HTML` or `Linkify::FORMAT_MARKDOWN`).
	 * @param bool $stripTags Remove HTML tags before processing.
	 * @param bool $strictWhitespace Apply strict whitespace normalization.
	 *
	 * @return string Processed text with detected patterns formatted as links.
	 *
	 * @example - Example:
	 * ```php
	 * echo Linkify::format(
	 *     'Visit https://example.com or email admin@example.com',
	 *     [Linkify::URLS, Linkify::EMAILS],
	 *     Linkify::FORMAT_HTML,
	 *     [
	 *         Linkify::URLS => [
	 *             'attributes' => [
	 *                 'target' => '_blank',
	 *                 'rel'    => 'noopener'
	 *             ]
	 *         ]
	 *     ]
	 * );
	 * ```
	 * 
	 *  @example - Global Option Style:
	 * ```php
	 * echo Linkify::format(
	 *     'Visit https://example.com or email admin@example.com',
	 *     [Linkify::URLS, Linkify::EMAILS],
	 *     Linkify::FORMAT_HTML,
	 *     [
	 *      	attributes' => [
	 *          	'target' => '_blank',
	 *          	'rel'    => 'noopener'
	 *     		]
	 *     ]
	 * );
	 * ```
	 * @see Linkify::urls()
	 * @see Linkify::maps()
	 * @see Linkify::mails()
	 * @see Linkify::phones()
	 * @see Linkify::mentions()
	 * @see Linkify::hashtags()
	 * @see Linkify::strip()
	 * 
	 * @see Linkify::formatAll()
	 * @see Linkify::stripAll()
	 */
	public static function format(
		string $text,
		array $types = [],
		array $options = [],
		string $format = self::FORMAT_HTML,
		bool $stripTags = true,
		bool $strictWhitespace = true
	): string
	{
		if ($text === '') {
			return '';
		}

		$text = Text::normalize($text, $stripTags, $strictWhitespace);

		if ($text === '') {
			return '';
		}

		$types = (array) $types;
		$allEnabled = $types === [];
		$enabled = $allEnabled ? [] : array_flip($types);
	
		$isGlobalOption = $options !== [] 
			&& (isset($options['mode'])
				|| isset($options['base'])
				|| isset($options['filter'])
				|| isset($options['attributes'])
			);

		foreach (self::OPTIONS as $type) {
			if ($allEnabled || isset($enabled[$type])) {
				$text = self::{$type}(
					$text,
					$format,
					$isGlobalOption ? $options : ($options[$type] ?? [])
				);
			}
		}

		return trim($text);
	}

	/**
	 * Converts all supported text patterns into clickable links.
	 *
	 * Detects and formats:
	 * - URLs
	 * - Email addresses
	 * - Phone numbers
	 * - User mentions
	 * - Hashtags
	 * - Map locations
	 *
	 * The output can be generated as HTML anchors or Markdown links.
	 * HTML tags can optionally be removed before processing.
	 *
	 * @param string $text Input text to format.
	 * @param string $format Output format:
	 * 						- `Linkify::FORMAT_HTML`     Generates HTML anchor links.
	 * 						- `Linkify::FORMAT_MARKDOWN` Generates Markdown links.
	 * @param string $target HTML anchor target attribute (e.g. `_blank`, `_self`).
	 *                       Applied only when using HTML format.
	 * @param bool $stripTags Whether to remove HTML tags before link processing.
	 * @param bool $strictWhitespace Whether to apply strict whitespace normalization.
	 *
	 * @return string Formatted text ready for rendering.
	 *
	 * @see Linkify::format()
	 * @see Linkify::strip()
	 */
	public static function formatAll(
		string $text,
		string $format = self::FORMAT_HTML,
		string $target = '_self',
		bool $stripTags = true,
		bool $strictWhitespace = true
	): string
	{
		if ($text === '') {
			return '';
		}

		if (
			$format !== self::FORMAT_HTML &&
			$format !== self::FORMAT_MARKDOWN
		) {
			return Text::normalize($text, $stripTags, $strictWhitespace);
		}

		$text = Text::normalize($text, $stripTags, $strictWhitespace);

		if ($text === '') {
			return '';
		}

		return self::format(
			$text,
			format: $format,
			options: [
				'attributes' => ($target !== '')
					? ['target' => $target]
					: []
			]
		);
	}

	/**
	 * Convert plain text URLs into clickable links.
	 *
	 * Detects HTTP/HTTPS URLs in a string and transforms them into HTML <a> tags
	 * or Markdown links depending on the selected mode.
	 *
	 * External URLs can optionally be redirected through a tracking or proxy URL,
	 * while internal URLs (same host or subdomain of APP_HOSTNAME) are left untouched.
	 *
	 * Options:
	 * - `base` (string): Base redirect URL for external links (default: `APP_URL . '?redirect='`).
	 * - `attributes` (array): Additional HTML attributes for the anchor tag
	 *   (e.g. ['target' => '_blank', 'rel' => 'nofollow'])
	 *
	 * @param string $text Input text containing raw URLs.
	 * @param string $format Output format: `Linkify::FORMAT_HTML` or `Linkify::HTML_MARKDOWN`.
	 * @param array{
 	 * 		base?: ?string,
 	 * 		mode?: 'link'|'strip',
	 * 		attributes?: array<string,string>,
	 * 		filter?: array{
	 * 			patterns: string[],
	 * 			dropUnmatched?: bool 
	 * 		}
	 * }|Option $options Optional settings:
	 *
	 * @return string Processed text with clickable links or removed.
	 *
	 * @example - Example:
	 * ```php
	 * $text = "Visit https://example.com and https://my-site.com/page";
	 *
	 * echo Linkify::urls($text, Linkify::FORMAT_HTML, [
	 *     'attributes' => [
	 *         'target' => '_blank',
	 *         'rel' => 'noopener noreferrer'
	 *     ]
	 * ]);
	 * ```
	 */
	public static function urls(
		string $text,
		string $format = self::FORMAT_HTML,
		array $options = []
	): string 
	{
		$base = $options['base'] ?? APP_URL . '?redirect=';

		return self::onLinkify(
			$text,
			'~(?P<value>(?:https?://|www\.)[^\s<>"\'(),.!?]+\.[a-z]{2,})~i',
			$format,
			$options,
			static function (string $url, string $attr) use ($format, $base): string {

				$url = rtrim($url, '.,);!?');

				if ($url === '') {
					return '';
				}

				$host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
				$appHost = strtolower(APP_HOSTNAME);

				$isInternal =
					$host === $appHost ||
					str_ends_with($host, '.' . $appHost) ||
					$host === 'www.' . $appHost;

				$href = $isInternal
					? $url
					: $base . rawurlencode($url);

				return self::buildHref(
					$format,
					$url,
					$href,
					$attr
				);
			}
		);
	}

	/**
	 * Convert map references into clickable map URLs.
	 *
	 * Detects valid map scheme and converts them into `base:` links
	 * depending on the selected output mode (HTML or Markdown).
	 *
	 * Supported formats:
	 * - map:Address
	 * - map:3.1390,101.6869
	 *
	 * @param string $text Input text containing email addresses.
	 * @param string $format Output format: `Linkify::FORMAT_HTML` or `Linkify::HTML_MARKDOWN`.
	 * @param array{
 	 * 		base?: ?string,
 	 * 		mode?: 'link'|'strip',
	 * 		attributes?: array<string,string>,
	 * 		filter?: array{
	 * 			patterns: string[],
	 * 			dropUnmatched?: bool 
	 * 		}
	 * }|Option $options Optional settings.
	 *
	 * @return string Text with map references converted to links or removed.
	 *
	 * @example - Example:
	 * ```php
	 * echo Linkify::maps("Contact me at map:Address", Linkify::FORMAT_HTML, [
	 *     'attributes' => ['class' => 'map-link']
	 * ]);
	 * ```
	 */
	public static function maps(
		string $text,
		string $format = self::FORMAT_HTML,
		array $options = []
	): string 
	{
		$base = $options['base'] ?? 'https://www.google.com/maps/search/?api=1&query=';

		return self::onLinkify(
			$text,
			'/(?P<value>map:(?:[-+]?\d{1,3}(?:\.\d+)?,-?\d{1,3}(?:\.\d+)?|[\w\s,.-]+))/',
			$format,
			$options,
			fn(string $map, string $attr): string => self::buildHref(
				$format,
				$map,
				$base . rawurlencode(substr($map, 4)),
				$attr
			)
		);
	}

	/**
	 * Convert email addresses into clickable mailto links.
	 *
	 * Detects valid email patterns and converts them into `mailto:` links
	 * depending on the selected output mode (HTML or Markdown).
	 *
	 * @param string $text Input text containing email addresses.
	 * @param string $format Output format: `Linkify::FORMAT_HTML` or `Linkify::HTML_MARKDOWN`.
	 * @param array{
 	 * 		mode?: 'link'|'strip',
	 * 		attributes?: array<string,string>,
	 * 		filter?: array{
	 * 			patterns: string[],
	 * 			dropUnmatched?: bool 
	 * 		}
	 * }|Option $options Optional settings.
	 *
	 * @return string Text with email addresses converted to links or removed.
	 *
	 * @example - Example:
	 * ```php
	 * echo Linkify::emails("Contact me at test@example.com", Linkify::FORMAT_HTML, [
	 *     'attributes' => ['class' => 'email-link']
	 * ]);
	 * ```
	 */
	public static function emails(
		string $text, 
		string $format = self::FORMAT_HTML, 
		array $options = []
	): string
	{
		return self::onLinkify(
			$text,
			'/(?<![A-Za-z0-9._%+-])(?P<value>[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,63})(?![A-Za-z0-9._%+-])/',
			$format,
			$options,
			fn(string $email, string $attr): string => (
				filter_var($email, FILTER_VALIDATE_EMAIL)
					? self::buildHref(
						$format,
						$email,
						'mailto:' . $email,
						$attr
					)
					: $email
			)
		);
	}

	/**
	 * Convert @mentions into clickable profile or search links.
	 *
	 * Detects user mentions in the format @username and converts them into
	 * navigable links using a configurable base URL.
	 *
	 * Options:
	 * - `base` (string): Base URL or query prefix for mention links (default: '?user=').
	 * - `attributes` (array): Anchor attributes.
	 *
	 * @param string $text Input text containing @mentions.
	 * @param string $format Output format: `Linkify::FORMAT_HTML` or `Linkify::HTML_MARKDOWN`.
	 * @param array{
 	 * 		base?: ?string,
 	 * 		mode?: 'link'|'strip',
	 * 		attributes?: array<string,string>,
	 * 		filter?: array{
	 * 			patterns: string[],
	 * 			dropUnmatched?: bool 
	 * 		}
	 * }|Option $options Optional settings.
	 * 
	 * @param array $options Optional settings:
	 *                       - base: string
	 *                       - attributes: array
	 *
	 * @return string Text with mentions converted to links or removed.
	 *
	 * @example - Example
	 * ```php
	 * echo Linkify::mentions("Hello @john", Linkify::FORMAT_HTML, [
	 *     'base' => '/user/'
	 * ]);
	 * ```
	 */
	public static function mentions(
		string $text, 
		string $format = self::FORMAT_HTML, 
		array $options = []
	): string
	{
		$base = $options['base'] ?? '?user=';

		return self::onLinkify(
			$text,
			'/(?<![A-Za-z0-9._%+-])@(?P<value>[A-Za-z0-9_.]{2,30}(?:\.[A-Za-z0-9_.]{1,30})?)(?![A-Za-z0-9_.])/',
			$format,
			$options,
			fn(string $user, string $attr): string => self::buildHref(
				$format, 
				'@' . $user, 
				$base . rawurlencode($user),
				$attr
			)
		);
	}

	/**
	 * Convert phone numbers into clickable tel: links.
	 *
	 * Detects numeric phone patterns (10–13 digits with optional + prefix)
	 * and converts them into tel: links.
	 *
	 * @param string $text Input text containing phone numbers.
	 * @param string $format Output format: `Linkify::FORMAT_HTML` or `Linkify::HTML_MARKDOWN`.
	 * @param array{
 	 * 		mode?: 'link'|'strip',
	 * 		attributes?: array<string,string>,
	 * 		filter?: array{
	 * 			patterns: string[],
	 * 			dropUnmatched?: bool 
	 * 		}
	 * }|Option $options Optional settings.
	 *
	 * @return string Text with phone numbers converted into links or removed.
	 *
	 * @example - Example:
	 * ```php
	 * echo Linkify::phones("Call me at +60123456789", Linkify::FORMAT_HTML);
	 * ```
	 */
	public static function phones(
		string $text, 
		string $format = self::FORMAT_HTML, 
		array $options = []
	): string
	{
		return self::onLinkify(
			$text,
			'/(?<!\d)(?P<value>\+?\d[\d\s\-]{8,18}\d)(?!\d)/',
			$format,
			$options,
			static function(string $tel, string $attr) use($format): string { 
				$tel = preg_replace('/[\s\-]/', '', $tel);

				return self::buildHref(
					$format, 
					$tel, 
					'tel:' . $tel,
					$attr
				);
			}
		);
	}

	/**
	 * Convert hashtags into clickable links.
	 *
	 * Detects words prefixed with # and converts them into navigable links
	 * using a configurable base URL.
	 *
	 * Options:
	 * - `base` (string): Base URL or query prefix for hashtags (default: '?tag=').
	 * - `attributes` (array): Anchor attributes.
	 *
	 * @param string $text Input text containing hashtags.
	 * @param string $format Output format: `Linkify::FORMAT_HTML` or `Linkify::HTML_MARKDOWN`.
	 * @param array{
 	 * 		base?: ?string,
 	 * 		mode?: 'link'|'strip',
	 * 		attributes?: array<string,string>,
	 * 		filter?: array{
	 * 			patterns: string[],
	 * 			dropUnmatched?: bool 
	 * 		}
	 * }|Option $options Optional settings.
	 *
	 * @return string Text with hashtags converted into links or removed.
	 *
	 * @example - Example:
	 * ```php
	 * echo Linkify::hashtags("Love #php and #luminova", Linkify::FORMAT_HTML, [
	 *     'base' => '/tag/'
	 * ]);
	 * ```
	 */
	public static function hashtags(
		string $text, 
		string $format = self::FORMAT_HTML, 
		array $options = []
	): string
	{
		$base = ($options['base'] ?? '?tag=');

		return self::onLinkify(
			$text,
			'/(?<!\w)#(?P<value>\p{L}[\p{L}\p{N}_]{0,49}|[0-9][\p{L}\p{N}_]{0,49})/u',
			$format,
			$options,
			fn(string $tag, string $attr): string => self::buildHref(
				$format, 
				'#' . $tag,
				$base . rawurlencode($tag),
				$attr
			)
		);
	}

	/**
	 * Remove matched link types from the text.
	 *
	 * Only the specified link types are stripped. Any unmatched text remains unchanged.
	 *
	 * @param string $text The text to process.
	 * @param string[]|Types[] $types List of link types to strip (e.g. `urls`, `emails`, `phones`).
	 *
	 * @return string The text with the matched link types removed.
	 * 
	 * @example - Examples:
	 * ```php
	 * $text = Linkify::strip('Visit at: https//::example.com, email: peter@example.com');
	 * // Visit at:, email:
	 * 
	 * $text = Linkify::strip(
	 * 		'Visit at: https//::example.com, email: peter@example.com',
	 * 		[Linkify::EMAILS]
	 * );
	 * // Visit at: https//::example.com, email:
	 * ```
	 */
	public static function strip(
		string $text,
		array $types = []
	): string
	{
		$allEnabled = $types === [];
		$types = $allEnabled  ? [] : array_flip($types);

		foreach(self::OPTIONS as $method){
			if ($allEnabled || isset($types[$method])) {
				$text = self::{$method}(
					$text, 
					options: [
						'mode' => self::MODE_STRIP
					]
				);
			}
		}

		return trim($text);
	}

	/**
	 * Remove all supported link types from the text.
	 *
	 * Any detected links are removed, leaving only the remaining text.
	 *
	 * @param string $text The text to process.
	 *
	 * @return string The text with all detected links removed.
	 * @example - Examples:
	 * ```php
	 * $text = Linkify::stripAll('Visit at: https//::example.com, email: peter@example.com');
	 * // Visit at:, email:
	 * ```
	 */
	public static function stripAll(string $text): string
	{
		return self::strip($text);
	}

	/**
	 * Generic linkifier logic for transforming matched tokens into links or removing them.
	 *
	 * Supports:
	 * - Email, phone, URL, hashtag, mention, map schemes, etc.
	 * - Regex-based token extraction using named group: (?P<value>...)
	 * - Pattern filtering: exact, wildcard (*), and regex patterns
	 * - Match control: apply to all or selective patterns
	 * - Unmatched handling: keep or drop unmatched tokens
	 *
	 * @param string   $text     Input text to process.
	 * @param string   $pattern  Regex pattern with named group "value".
	 * @param string   $format   Output format (html|markdown|etc).
	 * @param array    $options  Options:
	 *                           - pattern: array<string> Filter rules
	 *                           - mode: link|strip
	 *                           - unmatched: keep|drop
	 *                           - attributes: array HTML attributes
	 * @param Closure  $onLink   Renderer callback (value, attributes)
	 *
	 * @return string
	 */
	private static function onLinkify(
		string $text,
		string $pattern,
		string $format,
		array $options,
		Closure $onLink
	): string 
	{
		if ($text === '') {
			return $text;
		}

		$attr      = self::buildAttributes($options['attributes'] ?? []);
		$mode      = $options['mode'] ?? self::MODE_LINK;
		$filter    = (array) ($options['filter'] ?? []);
		$patterns  = (array) ($filter['patterns'] ?? []);
		$dropUnmatched = (bool) ($filter['dropUnmatched'] ?? false);

		if (
			$mode === self::MODE_LINK 
			&& !in_array($format, [self::FORMAT_HTML, self::FORMAT_MARKDOWN], true)
		) {
			return $text;
		}

		$matchAll = empty($patterns);

		return preg_replace_callback(
			$pattern,
			static function (array $m) use (
				$patterns,
				$mode,
				$dropUnmatched,
				$attr,
				$onLink,
				$matchAll
			): string {

				$value = $m['value'];

				if (!$matchAll && !self::isLinkMatch($value, $patterns)) {
					if ($mode === self::MODE_STRIP) {
						return $value;
					}

					return $dropUnmatched ? '' : $value;
				}

				if ($mode === self::MODE_STRIP) {
					return '';
				}

				return $onLink($value, $attr);
			},
			$text
		);
	}

	/**
	 * Test link patterns.
	 *
	 * @param string $value
	 * @param array $patterns
	 * 
	 * @return bool
	 */
	private static function isLinkMatch(string $value, array $patterns): bool
	{
		foreach ($patterns as $pattern) {
			if ($pattern === '') {
				continue;
			}

			if ($pattern[0] === '/' && @preg_match($pattern, '') !== false) {
				if (preg_match($pattern, $value)) {
					return true;
				}
				continue;
			}

			if (str_contains($pattern, '*')) {
				if (fnmatch($pattern, $value)) {
					return true;
				}
				continue;
			}

			if (strcasecmp($pattern, $value) === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build link html or markdown format.
	 *
	 * @param string $format
	 * @param string $text
	 * @param string $href
	 * @param string $attr
	 * 
	 * @return string
	 */
	private static function buildHref(
		string $format, 
		string $text, 
		string $href, 
		string $attr = ''
	): string
	{
		if ($format === self::FORMAT_HTML) {
			return '<a href="' . $href . '"' . $attr . '>' . $text . '</a>';
		}

		return '[' . $text . '](' . $href . ')';
	}

	/**
	 * Builds an HTML attribute string from an associative array.
	 *
	 * Array keys are used as attribute names, while values become the
	 * corresponding attribute values. Empty keys are ignored. Attributes
	 * with an empty string value are rendered as boolean attributes.
	 *
	 * Example:
	 * ```php
	 * ['target' => '_blank', 'rel' => 'noopener', 'download' => '']
	 * ```
	 *
	 * Produces:
	 * ```html
	 * target="_blank" rel="noopener" download
	 * ```
	 *
	 * @param array<string,string> $attributes HTML attribute name/value pairs.
	 *
	 * @return string Formatted HTML attribute string prefixed with spaces,
	 *                or an empty string if no valid attributes exist.
	 */
	private static function buildAttributes(array $attributes): string 
	{
		$attr = '';

		foreach($attributes as $key => $value){
			if($key === ''){
				continue;
			}
			if($value === ''){
				$attr .= " {$key}";
				continue;
			}

			$attr .= " {$key}=\"{$value}\"";
		}

		return $attr;
	}
}