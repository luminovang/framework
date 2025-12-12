<?php
/**
 * Luminova Framework Content Security Policy (CSP).
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Security;

use \Luminova\Http\Header;
use \Luminova\Exceptions\InvalidArgumentException;

/**
 * Content Security Policy (CSP) builder.
 *
 * Provides a fluent API for defining, modifying, and emitting
 * Content-Security-Policy directives.
 *
 * The policy can be:
 * - rendered as a CSP header string,
 * - sent directly as an HTTP response header,
 * - or embedded as an HTML `<meta>` tag.
 *
 * This class does not validate directive names or values.
 * It assumes the caller provides valid CSP syntax.
 * 
 * @see https://luminova.ng/docs/0.0.0/security/csp
 */
class CSP 
{
    /**
     * Keywords that must be quoted if used
     * 
     * @var array KEYWORDS
     */
    protected const KEYWORDS = [
        'self','unsafe-inline','unsafe-eval','none','strict-dynamic',
        'report-sample','wasm-unsafe-eval','unsafe-hashed-attributes'
    ];

    /**
     * Allowed schemes and values (used in validation)
     * 
     * @var array POLICIES
     */
    protected const POLICIES = [
        "data:", "blob:", "filesystem:", "mediastream:", 
        "about:", "http:", "https:"
    ];

    /** 
     * CSP directives and values.
     * 
     * @var array<string,array<string>> $directives
     */
    protected array $directives = [];

    /** 
     * Generated tokens for directives.
     * 
     * @var array<string,array<string,string>> $tokens
     */
    protected array $tokens = [];

    /** 
     * Generated CSP policies.
     * 
     * @var string|null $policies
     */
    protected ?string $policies = null;

    /**
     * CSP constructor.
     *
     * Initializes a new CSP instance. Optionally enables report-only mode.
     *
     * @param bool $reportOnly Whether to send the CSP as a report-only header. Default is false.
     */
    public function __construct(private bool $reportOnly = false){}

    /**
     * Generate a random nonce with an optional prefix.
     *
     * @param int $length The length of the random bytes to generate (default: 16).
     * @param string $prefix An optional prefix for the nonce (default: '').
     * 
     * @return string Return a cached generated script nonce.
     */
    public static final function randomNonce(int $length = 16, string $prefix = ''): string
    {
        return $prefix . base64_encode(random_bytes((int) ceil($length / 2)));
    }

    /**
     * Retrieve the generated nonce for a specific directive.
     *
     * Useful for injecting into inline `<script>` or `<style>` tags to comply with CSP.
     *
     * @param string $directive The CSP directive name (e.g., 'script-src').
     * 
     * @return string|null Returns the nonce string if it exists, 
     *          or null if no nonce was generated for this directive.
     */
    public function getNonce(string $directive): ?string
    {
        return $this->tokens['nonces'][$directive] ?? null;
    }

    /**
     * Retrieve the generated hash for a specific directive.
     *
     * Useful for injecting into inline `<script>` or `<style>` tags
     * that are allowed via a cryptographic hash in the CSP.
     *
     * @param string $directive The CSP directive name (e.g., 'script-src', 'style-src').
     * 
     * @return string|null Returns the Base64-encoded hash if it exists,
     *          or null if no hash was generated for this directive.
     */
    public function getHash(string $directive): ?string
    {
        return $this->tokens['hashes'][$directive] ?? null;
    }

    /**
     * Retrieve values of a specific CSP directive.
     *
     * @param string $directive The CSP directive name (e.g., 'script-src', 'style-src').
     * 
     * @return array<int,string> Returns an array of directive values.
     */
    public function get(string $directive): ?array
    {
        return $this->directives[$directive] ?? null;
    }

    /**
     * Retrieve full information about a specific CSP directive.
     *
     * Returns an associative array containing:
     *  - 'directive' => array|null The array of allowed sources for this directive, or null if unset.
     *  - 'nonce'     => string|null The generated nonce for inline scripts or styles, or null if none exists.
     *  - 'hash'      => string|null The generated hash for inline content, or null if none exists.
     *
     * @param string $directive The CSP directive name (e.g., 'script-src', 'style-src').
     * 
     * @return array{directive:?array,nonce:?string,hash:?string} Associative array of directive info.
     */
    public function toArray(string $directive): array
    {
        return [
            'directive' => $this->directives[$directive] ?? null,
            'nonce' => $this->tokens['nonces'][$directive] ?? null,
            'hash' => $this->tokens['hashes'][$directive] ?? null
        ];
    }

    /**
     * Add or extend a CSP directive.
     *
     * If the directive already exists, values are merged.
     * Duplicate values are removed when the policy is built.
     *
     * @param string $directive CSP directive name (e.g. `default-src`).
     * @param string|array $values One or more directive values.
     *
     * @return self Returns the instance of CSP class.
     * @throws InvalidArgumentException If the value is not valid for the specified directive.
     */
    public function add(string $directive, array|string $values): self
    {
        if($values === '' || $values === []){
            return $this;
        }

        $normalized = [];

        foreach ((array) $values as $value) {
            $value = trim($value);

            if($value === ''){
                continue;
            }

            $value = $this->normalizeValue($value);
            $this->assert($directive, $value);

            $normalized[] = $value;
        }

        $this->policies = null;

        if (!isset($this->directives[$directive])) {
            $this->directives[$directive] = [];
        }

        $this->directives[$directive] = array_merge(
            $this->directives[$directive], 
            $normalized
        );

        return $this;
    }

    /**
     * Add a nonce for a given directive.
     * 
     * If no nonce was provided, it will auto-generate a nonce for a directive and return it.
     *
     * @param string $directive Directive name (e.g., 'script-src').
     * @param string|null $nonce Optional nonce or randomly generated nonce.
     * @param int $length Length of random nonce (default 16 bytes).
     *
     * @return self Returns the instance of CSP class.
     * @see self::getNonce() - To get the generated nonce value.
     * 
     * @example - Example:
     * ```php
     * $csp = (new CSP())
     *      ->defaultSrc(['self', 'https://cdn.example.com'])
     *      ->scriptSrc(['self'])
     *      ->addNonce('script-src')
     *      ->reportOnly(false)
     * 
     * $nonce = $csp->getNonce('script-src');
     * echo "<script nonce=\"$nonce\">console.log('Hello');</script>";
     * ```
     */
    public function addNonce(string $directive, ?string $nonce = null, int $length = 16): self
    {
        $nonce ??= self::randomNonce($length);
        $nonce = trim($nonce);

        if ($nonce !== '') {
            $this->add($directive, "'nonce-{$nonce}'");
            $this->tokens['nonces'][$directive] = $nonce;
        }

        return $this;
    }

    /**
     * Add a cryptographic hash to a specific CSP directive.
     *
     * Useful for allowing inline scripts or styles without using nonces.  
     * Automatically generates a random hash if `$hash` is not provided.
     *
     * @param string $directive Directive name (e.g., 'script-src', 'style-src').
     * @param string $algo Hash algorithm ('sha256', 'sha384', 'sha512').
     * @param string|null $hash Optional Base64-encoded hash. If null, a random hash is generated.
     *
     * @return self Returns the instance of CSP class.
     * @throws InvalidArgumentException If the algorithm is not supported or the hash is empty/invalid.
     */
    public function addHash(string $directive, string $algo, ?string $hash = null): self
    {
        $algo = strtolower(trim($algo));

        $algos = ['sha256', 'sha384', 'sha512'];
        if (!in_array($algo, $algos, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid hash algorithm "%s". Allowed algorithms: %s.',
                $algo,
                implode(', ', $algos)
            ));
        }

        $hash ??= base64_encode(hash($algo, uniqid($directive, true), true));
        $hash = trim($hash);

        if ($hash === '') {
            throw new InvalidArgumentException('Hash value cannot be empty.');
        }

        $this->add($directive, "{$algo}-{$hash}");
        $this->tokens['hashes'][$directive] = $hash;

        return $this;
    }

    /**
     * Set report-only mode.
     *
     * @param bool $enable Wether to use report-only mode.
     * 
     * @return self Returns the instance of CSP class.
     */
    public function reportOnly(bool $enable = true): self
    {
        $this->reportOnly = $enable;
        return $this;
    }

    /**
     * Remove a CSP directive.
     *
     * @param string $directive The CSP directive to remove.
     * 
     * @return self Returns the instance of CSP class.
     */
    public function remove(string $directive): self
    {
        unset(
            $this->directives[$directive], 
            $this->tokens['nonces'][$directive],
            $this->tokens['hashes'][$directive]
        );

        if($this->directives === []){
            return $this->clear();
        }

        return $this;
    }

    /**
     * Remove all CSP directives.
     *
     * @return self Returns the instance of CSP class.
     */
    public function clear(): self
    {
        $this->directives = [];
        $this->tokens = [];
        $this->policies = null;

        return $this;
    }

    /**
     * Build the CSP policy string.
     *
     * @return string Returns the CSP policy string in the correct format.
     */
    public function build(): string
    {
        if($this->policies && $this->policies !== null){
            return $this->policies;
        }

        $policies = [];

        foreach ($this->directives as $directive => $values) {
            if($values === []){
                continue;
            }

            $policies[] = $directive . ' ' . implode(' ', array_unique($values));
        }

        return $this->policies = implode('; ', $policies);
    }

    /**
     * Generate an HTML `<meta>` tag containing the CSP policy.
     *
     * @param string $id Optional element ID (default: none).
     * 
     * @return string Returns the CSP `<meta>` tag markup.
     */
    public function getMetaTag(string $id = ''): string
    {
        $id = ($id !== '') ? 'id="' . htmlspecialchars($id, ENT_QUOTES) . '" ' : '';
        return '<meta 
            http-equiv="Content-Security-Policy" '. $id .
            'content="' . htmlspecialchars($this->build(), ENT_QUOTES, 'UTF-8') . 
            '">';
    }

    /**
     * Retrieve the Content-Security-Policy as an HTTP header.
     *
     * Returns the appropriate header name and policy value.  
     * Automatically selects `Content-Security-Policy` or 
     * `Content-Security-Policy-Report-Only` based on the report-only mode.
     *
     * @return array<string,string> Returns an array containing the header name at index 0 
     *         and the CSP value at index 1.
     */
    public function getHeaders(): array
    {
        $headers = [];
        $name = $this->reportOnly 
            ? 'Content-Security-Policy-Report-Only' 
            : 'Content-Security-Policy';

        $headers[$name] = $this->build();

        $endpoints = $this->tokens['endpoints'] ?? [];

        if ($endpoints !== []) {
            $reporting = [];

            foreach ($endpoints as $group => $endpoint) {
                $reporting[] = "{$group}=\"{$endpoint}\"";
            }

            $headers['Reporting-Endpoints'] = implode(', ', $reporting);
        }

        return $headers;
    }

    /**
     * Send the Content-Security-Policy as an HTTP header.
     *
     * Automatically sends the correct header for CSP or CSP-Report-Only if headers
     * have not already been sent. Safe to call multiple times; will not override 
     * headers already sent.
     *
     * @return void
     */
    public function sendHeader(): void
    {
        Header::send($this->getHeaders(), true, validateRequestHeaders: false);
    }

    /**
     * Set the `default-src` directive for the Content-Security-Policy.
     *
     * Defines default sources for all content types unless overridden by more specific directives.
     *
     * @param array|string $values The allowed sources (e.g., `'self'`, `'https://cdn.example.com'`).
     * 
     * @return self Returns the instance of CSP class.
     * @throws InvalidArgumentException If any value is invalid.
     */
    public function defaultSrc(array|string $values): self 
    { 
        return $this->add('default-src', $values); 
    }

    /**
     * Set the `script-src` directive.
     *
     * Specifies allowed sources for JavaScript, including external files and inline scripts (via nonce or hash).
     *
     * @param array|string $values Allowed script sources.
     * 
     * @return self Returns the instance of CSP class.
     * @throws InvalidArgumentException If any value is invalid.
     */
    public function scriptSrc(array|string $values): self 
    { 
        return $this->add('script-src', $values); 
    }

    /**
     * Set the `style-src` directive.
     *
     * Specifies allowed sources for CSS, including inline styles (via nonce or hash) and external stylesheets.
     *
     * @param array|string $values Allowed style sources.
     * 
     * @return self Returns the instance of CSP class.
     * @throws InvalidArgumentException If any value is invalid.
     */
    public function styleSrc(array|string $values): self 
    { 
        return $this->add('style-src', $values); 
    }

    /**
     * Set the `img-src` directive.
     *
     * Specifies allowed sources for images.
     *
     * @param array|string $values Allowed image sources.
     * 
     * @return self Returns the instance of CSP class.
     * @throws InvalidArgumentException If any value is invalid.
     */
    public function imgSrc(array|string $values): self 
    { 
        return $this->add('img-src', $values); 
    }

    /**
     * Set the `connect-src` directive.
     *
     * Defines allowed endpoints for AJAX, WebSockets, EventSource, and fetch requests.
     *
     * @param array|string $values Allowed connection sources.
     * 
     * @return self Returns the instance of CSP class.
     * @throws InvalidArgumentException If any value is invalid.
     */
    public function connectSrc(array|string $values): self 
    { 
        return $this->add('connect-src', $values); 
    }

    /**
     * Set the `font-src` directive.
     *
     * Specifies allowed sources for web fonts.
     *
     * @param array|string $values Allowed font sources.
     * 
     * @return self Returns the instance of CSP class.
     * @throws InvalidArgumentException If any value is invalid.
     */
    public function fontSrc(array|string $values): self 
    { 
        return $this->add('font-src', $values); 
    }

    /**
     * Set the `media-src` directive.
     *
     * Specifies allowed sources for audio and video media.
     *
     * @param array|string $values Allowed media sources.
     * 
     * @return self Returns the instance of CSP class.
     * @throws InvalidArgumentException If any value is invalid.
     */
    public function mediaSrc(array|string $values): self 
    { 
        return $this->add('media-src', $values); 
    }

    /**
     * Set the `object-src` directive.
     *
     * Specifies allowed sources for `<object>`, `<embed>`, and `<applet>` elements.
     *
     * @param array|string $values Allowed object sources.
     * 
     * @return self Returns the instance of CSP class.
     * @throws InvalidArgumentException If any value is invalid.
     */
    public function objectSrc(array|string $values): self 
    { 
        return $this->add('object-src', $values); 
    }

    /**
     * Set the `frame-src` directive.
     *
     * Specifies allowed sources for nested browsing contexts like `<iframe>`.
     *
     * @param array|string $values Allowed frame sources.
     * 
     * @return self Returns the instance of CSP class.
     * @throws InvalidArgumentException If any value is invalid.
     */
    public function frameSrc(array|string $values): self 
    { 
        return $this->add('frame-src', $values); 
    }

    /**
     * Add a 'report-uri' directive for CSP reporting.
     *
     * @param string $uri The endpoint URI where violation reports will be sent.
     *
     * @return self Returns the instance of CSP class.
     * @throws InvalidArgumentException If the URI is empty or invalid.
     */
    public function reportUri(string $uri): self
    {
        return $this->addReportEndpoint('report-uri', trim($uri));
    }

    /**
     * Add a 'report-to' directive for modern CSP reporting using the Reporting API.
     *
     * This method registers a Reporting API group and its endpoint. Browsers will send 
     * CSP violation reports to the specified endpoint via the `report-to` directive. 
     * The corresponding `Reporting-Endpoints` header should also be sent with the same group.
     *
     * @param string $group The Reporting API group name (used in `report-to` directive).
     * @param string $endpoint The URL endpoint where reports for this group will be sent.
     *
     * @return self Returns the instance of CSP class.
     * @throws InvalidArgumentException If the group name or endpoint is empty.
     *
     * @example - Usage:
     * ```php
     * $csp = new CSP(reportOnly: true);
     * $csp->reportTo('csp-group', '/csp/report-endpoint');
     * 
     * // Sends headers:
     * // Content-Security-Policy-Report-Only: ...; report-to csp-group
     * // Reporting-Endpoints: csp-group="/csp/report-endpoint"
     * ```
     */
    public function reportTo(string $group, string $endpoint): self
    {
        $group = trim($group);
        $endpoint = trim($endpoint);

        $this->addReportEndpoint('report-to', $group);
        $this->tokens['endpoints'][$group] = $endpoint;

        return $this;
    }

    /**
     * Add a 'report-to' or 'report-uri' directive for CSP reporting.
     *
     * @param string $directive The directive
     * @param string $to The Reporting URI or API group.
     *
     * @return self Returns the current CSP instance.
     * @throws InvalidArgumentException If the group name is empty or invalid.
     */
    private function addReportEndpoint(string $directive, string $to, ?string $endpoint = null): self
    {
        $isLegacy = ($directive ==='report-to');

        if ($to === '' || ($isLegacy && $endpoint === '')) {
            throw new InvalidArgumentException(sprintf(
                '%s cannot be empty.', 
                $isLegacy
                    ? (($endpoint === '') ? 'Report-To group and endpoint' : 'Report-To group name')
                    : 'Report URI'
            ));
        }

        return $this->add($directive, $to);
    }
    
    /**
     * Validate a CSP value for a given directive.
     *
     * Checks whether the provided value is allowed for Content-Security-Policy rules.
     * Accepts CSP keywords, nonces, hashes, URL schemes, and standard policies.
     * 
     * @param string $directive The CSP directive name (e.g., 'script-src', 'default-src').
     * @param string $value The CSP value to validate (e.g., `'self'`, `'nonce-abc123'`, `sha256-xyz...`, `https:`).
     * @return void
     *
     * @throws InvalidArgumentException If the value is not valid for the specified directive.
     */
    protected function assert(string $directive, string $value): void
    {
        if (
            preg_match("/^'nonce-[\w+/=]+'$/", $value) ||
            preg_match("/^sha(256|384|512)-[\w+/=]+$/", $value)
        ) {
            return;
        }

        if (
            in_array($value, array_map(fn($k) => "'$k'", self::KEYWORDS), true) ||
            in_array($value, self::POLICIES, true) ||
            preg_match("/^[a-z]+:\/\//", $value)
        ) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid CSP value "%s" for directive "%s".',
            $value,
            $directive
        ));
    }

    /**
     * Normalize a CSP value for safe inclusion in a policy.
     *
     * - Wraps CSP keywords (e.g., `self`, `none`) and unquoted nonces in single quotes.
     * - Leaves already quoted values, nonces, and hashes (`sha256`, `sha384`, `sha512`) unchanged.
     * - Leaves URL schemes (e.g., `https:`) unchanged.
     *
     * @param string $value The CSP value to normalize.
     * @return string The normalized CSP value, ready to be added to a directive.
     */
    protected function normalizeValue(string $value): string
    {
        $value = trim($value);

        if (
            (str_starts_with($value, "'") && str_ends_with($value, "'")) ||
            preg_match("/^sha(256|384|512)-[\w+/=]+$/", $value) ||
            preg_match("/^'nonce-[\w+/=]+'$/", $value)
        ) {
            return $value;
        }

        if (preg_match("/^nonce-[\w+/=]+$/", $value) || in_array($value, self::KEYWORDS, true)) {
            return "'$value'";
        }

        return $value;
    }
}