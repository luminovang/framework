<?php 
/**
 * Luminova Framework HTTP Webhooks.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http;

use \Closure;
use \Throwable;
use \JsonException;
use \Luminova\Luminova;
use \Luminova\Http\Request;
use \Luminova\Http\Network\IP;
use \Luminova\Http\Client\Novio;
use \Luminova\Interface\{ResponseInterface, LazyObjectInterface};
use \Luminova\Exceptions\{
    LogicException, 
    RuntimeException, 
    EncryptionException,
    InvalidArgumentException,
    Http\RequestException
};

/**
 * A simple and secure webhook handler for sending or receiving HTTP requests.
 *
 * Supports:
 * - Sending requests with custom payloads and headers
 * - HMAC signature generation for verification
 * - Listening for incoming requests and validating signatures
 */
class Webhook implements LazyObjectInterface
{
    /**
     * Configuration key used to define the webhook event name in the payload.
     * 
     * @var string EVENT_KEY
     */
    protected final const EVENT_KEY = 'app.webhook.event';

    /**
     * Configuration key used to indicate whether the payload should be signed.
     *
     * @var string SIGN_KEY
     */
    protected final const SIGN_KEY = 'app.webhook.signable';

    /**
     * Custom headers to include in the outgoing webhook request.
     * 
     * @var array<string,mixed> $headers
     */
    protected array $headers = [];

    /**
     * The hashing algorithm used for HMAC signing (default: sha256).
     * 
     * @var string $algo
     */
    protected string $algo = 'sha256';

    /**
     * The name of the HTTP header used to transmit the payload signature.
     * 
     * @var string|null $xSignature
     */
    protected ?string $xSignature = 'X-Signature';

    /**
     * Salt key used in generating random secret for payloads sign signature.
     * 
     * @var string $salt
     */
    protected string $salt = 'app.webhook.salt';

    /**
     * Indicates whether the webhook payload should be signed with HMAC.
     * 
     * @var bool $isSignable
     */
    private bool $isSignable = false;

    /**
     * Metadata from the incoming webhook request.
     * 
     * @var array<string,mixed> $incoming
     */
    private array $incoming = [];

    /**
     * The main payload body to send with the webhook request.
     * 
     * @var array<string,mixed> $payload
     */
    private array $payload = [];

    /**
     * Decoded payload after validation or unpacking.
     * 
     * @var array|null $decoded
     */
    private ?array $decoded = null;

    /**
     * Signature value from the incoming request.
     * 
     * @var string|null $signature
     */
    private ?string $signature = null;

    /**
     * Secret key used for signing or verifying the payload.
     * 
     * @var string|null $secret
     */
    private ?string $secret = null;

    /**
     * Raw payload string.
     * 
     * @var string|null $rawPayload
     */
    private ?string $rawPayload = null;

    /**
     * If webhook is in listening mode.
     * 
     * @var bool $isListening
     */
    private bool $isListening = false;

    /**
     * HTTP request object.
     * 
     * @var Request|null $request
     */
    private static ?Request $request = null;

    /**
     * Create a new webhook client instance.
     *
     * Initializes the webhook with the target URL, request method,
     * timeout, and default sending state.
     *
     * @param string $url Target webhook endpoint URL.
     * @param string $method HTTP request method (default: `POST`).
     * @param int $timeout Request timeout in seconds (default: `0` no timeout).
     */
    public function __construct(
        private string $url,
        protected string $method = 'POST',
        protected int $timeout = 0
    )
    {
        $this->setMethod($this->method);
        $this->isListening = false;
        $this->incoming = [];
    }

    /**
     * Retrieve the webhook payload.
     *
     * This method returns either the full payload if validation succeeded otherwise
     *
     * @return array|string|null Return decoded payload or empty if failed.
     */
    public function getPayload(): mixed
    {
        return $this->payload['_data'] 
            ?? $this->payload;
    }

    /**
     * Get the HMAC signature generated for the payload.
     *
     * @return string|null Returns the signature string, or null if not generated.
     */
    public function getSignature(): ?string
    {
        return $this->signature;
    }

    /**
     * Get the secret key used for signing or verifying the signature.
     *
     * @return string|null Returns the secret key, or null if not set.
     */
    public function getSecret(): ?string
    {
        return $this->secret;
    }

    /**
     * Set the HTTP method to use for sending the request.
     *
     * @param string $method The HTTP method (e.g., GET, POST, PUT, DELETE).
     * 
     * @return static Return instance of webhook class.
     */
    public function setMethod(string $method): self
    {
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * Set custom headers to send with the request.
     *
     * @param array<string,string> $headers Associative array of headers.
     * 
     * @return static Return instance of webhook class.
     *
     * @example - Example Usage: 
     * 
     * ```php
     * $webhook->setHeaders([
     *     'Accept' => 'application/json',
     *     'X-App-Token' => 'abc123',
     * ]);
     * ```
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Set the webhook request payload.
     * 
     * The sets the HTTP webhook request body to be sign and send.
     *
     * @param array|string $payload Payload can be an array or a raw JSON string.
     * 
     * @return static Return instance of webhook class.
     *
     * @example - Example Usage: 
     * 
     * ```php
     * $webhook->setPayload(['event' => 'user.created']);
     * 
     * // Or String
     * $webhook->setPayload('user.created');
     * 
     * // Or String Json
     * $webhook->setPayload('{"event":"user.created"}');
     * ```
     */
    public function setPayload(array|string $payload): self
    {
        $this->payload['_data'] = $payload;
        return $this;
    }

    /**
     * Adds a key-value pair to the webhook payload.
     *
     * @param string $name  The key to add to the payload.
     * @param mixed  $value The value to assign to the given key.
     * 
     * @return static Return instance of webhook class.
     * 
     * @example - Example Usage: 
     * 
     * ```php
     * $webhook->addPayload('event', 'user.created');
     * ```
     */
    public function addPayload(string $name, mixed $value): self
    {
        $payload = $this->payload['_data'] ?? [];

        if(!is_array($payload)){
            $payload = [$payload];
        }

        $payload[$name] = $value;
        $this->payload['_data'] = $payload;

        return $this;
    }

    /**
     * Set payload signature secrete.
     * 
     * The secret key is used for signing outgoing and verifying incoming request payload.
     *
     * @param string $secret The shared secret key.
     * 
     * @return static Return instance of webhook class.
     */
    public function setSecret(string $secret): self
    {
        $this->secret = $secret;
        return $this;
    }

    /**
     * Define the outgoing webhook event name.
     *
     * This sets the event identifier that will be sent in the payload under the
     * internal `_hook` structure using the predefined `EVENT_KEY`.
     *
     * Has no effect when the instance is in listening mode.
     *
     * @param string $name Event name (e.g. 'user.created', 'payment.failed').
     *
     * @return static Return instance of webhook class.
     */
    public function setEvent(string $name): self
    {
        if($this->isListening){
            return $this;
        }

        $this->payload['_hook'][self::EVENT_KEY] = $name;

        return $this;
    }

    /**
     * Define accepted incoming webhook event names.
     *
     * This registers the events that this listener will accept when processing
     * incoming webhook requests.
     *
     * Only applies when the instance is in listening mode.
     *
     * @param string|string[] $name Event name(s) to accept (e.g. 'user.created', 'payment.failed').
     *
     * @return static Return instance of webhook class.
     * @see self::listen()
     */
    public function setAcceptEvents(array|string $name): self
    {
        return $this->setIncoming('accept.events', $name);
    }

    /**
     * Define the event key(s) used to extract and resolve incoming the webhook event name.
     *
     * Some webhook providers (e.g. PayStack) include the event name under
     * a custom payload key such as `event` instead of the default `_hook` structure.
     * This method allows you to register a key that should be used
     * to resolve the incoming event name.
     *
     * @param string $name The payload key that contains the event name.
     *
     * @return static Return instance of webhook class.
     * @example  - Example:
     * ```php
     * $webhook->setEventField('event');
     * ```
     * Example payload:
     * `{ "event": "charge.success", "data": { ... } }`
     */
    public function setEventField(string $name): self
    {
        $this->incoming['accept.event.filed'] = $name;
        return $this;
    }

    /**
     * Define allowed origins for incoming webhook requests.
     *
     * This restricts which request origins are permitted when the webhook
     * is operating in listening mode. Multiple origins can be added either
     * as a single string or an array of values.
     *
     * Has no effect when the instance is not in listening mode.
     *
     * @param string|string[] $origin One or more allowed origin URLs or domains.
     *
     * @return static Return instance of webhook class.
     * @see self::setAllowEmptyOrigin() To allow non-browser client.
     * @see \App\Config\Security For global origin configuration.
     */
    public function setAllowedOrigins(array|string $origin = ['*']): self
    {
        return $this->setIncoming('accept.origins', $origin);
    }

    /**
     * Allow or deny requests without an Origin header.
     *
     * When enabled, requests that do not include an Origin header (e.g. server-to-server
     * or CLI requests) will be accepted. When disabled, such requests will be rejected.
     *
     * @param bool $allow Whether to allow requests with no Origin header.
     *
     * @return static Return instance of webhook class.
     */
    public function setAllowEmptyOrigin(bool $allow = true): self
    {
        return $this->setIncoming('accept.empty.origin', $allow);
    }

    /**
     * Add one or more IP addresses to the blacklist.
     *
     * Requests originating from these IPs will be denied during validation.
     *
     * @param string|string[] $ip IP address or list of addresses to block.
     *
     * @return static Return instance of webhook class.
     */
    public function addIPBlacklist(array|string $ip): self
    {
        return $this->setIncoming('ip.blacklisted', $ip);
    }

    /**
     * Add one or more IP addresses to the whitelist.
     *
     * When defined, only requests from these IPs will be accepted.
     *
     * @param string|string[] $ip IP address or list of allowed addresses.
     *
     * @return static Return instance of webhook class.
     */
    public function addIPWhitelist(array|string $ip): self
    {
        return $this->setIncoming('ip.whitelisted', $ip);
    }

    /**
     * Set the request timeout duration in seconds.
     *
     * @param int $seconds Number of seconds before the request times out.
     * 
     * @return static Return instance of webhook class.
     *
     * @example - Example Usage: 
     * 
     * ```php
     * $webhook->setTimeout(30); // 30 second timeout
     * ```
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Set the HMAC hashing algorithm used for signing the webhook payload.
     *
     * This defines the algorithm used in signature generation and verification (e.g., sha256, sha512).
     *
     * @param string $algo The name of the hashing algorithm (default: 'sha256').
     * 
     * @return static Return instance of webhook class.
     */
    public function setAlgo(string $algo): self
    {
        $this->algo = $algo;

        return $this;
    }

    /**
     * Define the header name where the HMAC signature will be attached.
     *
     * This sets the HTTP header used to transmit the payload signature (e.g., `X-Signature`, `X-MyApp-Signature`).
     *
     * @param string $xSignature The custom header name for the signature (default: `X-Signature`).
     * 
     * @return static Return instance of webhook class.
     */
    public function setSignatureHeaderName(string $xSignature): self
    {
        $this->xSignature = $xSignature;

        return $this;
    }

    /**
     * Enable HMAC signing for the webhook payload.
     *
     * This method marks the payload for signing. When the webhook is sent, an HMAC signature
     * will be generated and included in the request headers under the defined signature header.
     *
     * @param string|null $secret Optional secret key for signing. 
     *                      If omitted, the value from `setSecret()` is used if available.
     * 
     * @return static Return instance of webhook class.
     *
     * @example  - Example Usage: 
     * ```php
     * $webhook->setPayload(['event' => 'update'])
     *      ->sign()
     *      ->setAlgo('sha256')
     *      ->setSignatureHeaderName('X-MyApp-Signature'); // $request->header->get('X-Myapp-Signature')
     * ```
     *
     * > **Note:** If no secret is provided and none was set previously, a random secret will be generated.
     *       You can retrieve the key used by calling `getSecret()` after sending.
     */
    public function sign(?string $secret = null): self
    {
        $this->isSignable = true;
        $this->payload['_hook'][self::SIGN_KEY] = 1;

        if($secret){
            $this->secret = $secret;
        }

        return $this;
    }

    /**
     * Create a webhook listener for incoming request verification.
     *
     * This method creates a new webhook instance in listening mode and prepares it
     * to validate incoming payloads, signatures, events, origins, and client IPs.
     *
     * If no signature is passed and no signature header name is configured,
     * signature verification will be skipped. To resolve the signature
     * automatically from the request headers, call `setSignatureHeaderName()`.
     *
     * @param string|null $signature Optional request signature (for example, from the X-Signature header).
     * @param string|array<int,string> $events Optional event name or list of allowed events to accept.
     *
     * @return static Returns a new webhook listener instance.
     *
     * @see self::request() Create outgoing request object.
     * @see self::unpack() Verify and process the incoming request.
     * @see self::setEventField() Set the payload field used for the event name.
     * @see self::setAcceptEvents() Define allowed event names.
     * @see self::setSignatureHeaderName() Set the request header used for the signature.
     * @see self::setAllowedOrigins() Restrict allowed request origins.
     * @see self::setAllowEmptyOrigin() Allow requests without an Origin header.
     * @see self::addIPBlacklist() Block specific client IPs.
     * @see self::addIPWhitelist() Allow only trusted client IPs.
     *
     * @example - Create and verify an incoming webhook:
     * ```php
     * $webhook = Webhook::listen(
     *     $request->header->get('X-Signature'),
     *     'event-name'
     * )
     * ->setAlgo('sha256')
     * ->setSecret('your-secret-key');
     *
     * $webhook->unpack(fn (mixed $data) => store($data));
     * ```
     */
    public static function listen(?string $signature = null, string|array $events = []): static
    {
        $instance = new static('');
        $instance->signature = $signature;
        $instance->xSignature = null;
        $instance->isListening = true;
        $instance->incoming = [
            'onIncoming' => 1, 
            'ip.whitelisted' => [],
            'ip.blacklisted' => [],
            'accept.event.filed' => [],
            'accept.empty.origin' => false,
            'accept.events' => is_array($events) ? $events : [$events],
            'accept.origins' => [],
            'accept.event.filed' => '_event',
        ];

        return $instance;
    }

    /**
     * Create a webhook client for sending requests.
     *
     * This is a shortcut factory for creating a new webhook instance
     * with the target URL, HTTP method, and request timeout.
     *
     * @param string $url Target webhook endpoint URL.
     * @param string $method HTTP request method to use (default: `POST`).
     * @param int $timeout Request timeout in seconds. Use `0` for no timeout.
     *
     * @return static Returns a new webhook instance for request client.
     * @throws InvalidArgumentException If the URL is empty or invalid.
     * 
     * @see self::listen() For handling incoming request.
     * @see self::send() To sent HTTP request for prepared webhook payload.
     *
     * @example Create a basic webhook client
     * ```php
     * $webhook = Webhook::request('https://example.com/webhook')
     *     ->setPayload(['status' => 'OK'])
     *     ->send();
     * ```
     */
    public static function request(
        string $url,
        string $method = 'POST',
        int $timeout = 0
    ): static
    {
        if(!$url){
            throw new InvalidArgumentException(
                'Provide a valid webhook request URL.'
            );
        }

        return new static($url, $method, $timeout);
    }

    /**
     * Verify and unpack an incoming webhook request.
     *
     * This method validates the request origin, IP rules, listener state,
     * and payload signature before returning the decoded data.
     *
     * If validation succeeds:
     * - returns the decoded payload, or
     * - passes it to the callback and returns the callback result.
     *
     * If validation fails:
     * - throws an exception in development, or
     * - sends an error response and terminates request in production.
     *
     * @param (Closure(mixed):mixed)|null $onResponse Optional callback to process the verified payload.
     * @param array<string,mixed>|null $data Optional payload to verify instead of the current request body.
     *
     * @return mixed Returns the decoded payload, callback result, or false if terminated in production.
     * @throws RuntimeException If verification fails in development mode.
     *
     * @example - Process verified payload
     * ```php
     * $webhook = Webhook::listen(...);
     *
     * $webhook->unpack(function (mixed $payload): mixed {
     *     // Handle verified webhook data
     *     return true;
     * });
     * ```
     *
     * @example - Get verified payload directly
     * ```php
     * webhook = Webhook::listen(...);
     * 
     * $data = $webhook->unpack();
     * ```
     *
     * > **Note:** 
     * > You may also pass raw decoded request data manually for debugging.
     */
    public function unpack(?Closure $onResponse = null, ?array $data = null): mixed
    {
        $allowed = null;
        $error = null;

        try{
            if (!$this->isOriginAllowed($allowed)) {
                $error = [403, 'Access denied: request origin not allowed.'];
            } elseif(!$this->isWhitelisted() || $this->isBlacklisted()){
                $error = [401, 'Access denied: client not authorized.'];
            } elseif(!$this->isListening) {
                $error = [500, 'Invalid state: webhook is not in listening mode.'];
            } elseif(!$this->isSignature($data)) {
                $error = $this->decoded 
                    ? [406, 'Signature mismatch. Data may have been tampered with.']
                    : [400, 'No request data to handle.'];
            } else {
                // if ($allowed) {
                //     Header::send(['Access-Control-Allow-Origin' => $allowed], status: 200);
                // } else {
                //    Header::sendStatus(200);
                // }

                $payload = $this->decoded['_data'] ?? $this->decoded;

                if ($onResponse instanceof Closure) {
                    return $onResponse($payload);
                }

                return $payload;
            }
        } catch (Throwable $e) {
            $error = [500, $e->getMessage()];
        }

        $this->free();

        if(!PRODUCTION){
            throw new RuntimeException($error[1]);
        }

        Luminova::terminate(...$error);
        return false;
    }

    /**
     * Check if the current request IP is whitelisted.
     *
     * @return bool Returns true if the request IP matches a whitelist, false otherwise.
     * @see self::addIPWhitelist()
     */
    public function isWhitelisted(): bool
    {
        return $this->hasIPAddress('ip.whitelisted');
    }

    /**
     * Check if the current request IP is blacklisted.
     *
     * @return bool Returns true if the request IP matches a blacklist, false otherwise.
     * @see self::addIPBlacklist()
     */
    public function isBlacklisted(): bool
    {
        return $this->hasIPAddress('ip.blacklisted');
    }

    /**
     * Verify the webhook payload against its HMAC signature.
     *
     * This method validates the incoming request using the provided or incoming detected payload
     * and compares the HMAC digest to the signature. If an event name is provided in the context,
     * it is also validated against the payload.
     *
     * @param array|null $data Optional payload to validate (defaults to request body).
     * 
     * @return bool Return true if the payload and signature match; false otherwise.
     */
    public function isSignature(?array $data = null): bool
    {
        if (!$this->doCapture($data)) {
            return false;
        }

        if(!$this->rawPayload){
            return false;
        }

        $isInternal = isset($this->decoded['_hook']['webhook.internal']);

        if(!$isInternal && !$this->signature && !$this->xSignature){
            return true;
        }

        if(!$this->signature && $this->xSignature){
            self::$request ??= Request::getInstance();
            $this->signature = self::$request->header->get($this->xSignature);
        }

        if(!$this->signature){
            return true;
        }

        $accepts = $this->incoming['accept.events'] ?? null;

        if($accepts){
            $event = $this->decoded['_hook'][self::EVENT_KEY] ?? null;

            if(!$isInternal){
                $name = $this->incoming['accept.event.filed'] ?? '_event';
                $event = $this->decoded[$name] 
                    ?? $this->decoded['event'] 
                    ?? null;

                if(!is_string($event)){
                    $event = null;
                }
            }

            if (!$event || !in_array($event, (array) $accepts, true)) {
                return false;
            }
        }

        if($isInternal && !isset($this->decoded['_hook'][self::SIGN_KEY])){
            return true;
        }

        if (!$this->signature) {
            return false;
        }

        return $this->secret && hash_equals(
            hash_hmac($this->algo, $this->rawPayload, $this->secret), 
            $this->signature
        );
    }

    /**
     * Check whether the current request origin is allowed.
     *
     * If no allowed origins are configured, all requests are accepted.
     * Same-origin requests are always allowed. For cross-origin requests,
     * the request origin must match a configured allowed origin.
     *
     * @param string|null $allowed Matched allowed origin, if found.
     *
     * @return bool Returns true if the request origin is allowed, otherwise false.
     * @see self::setAllowedOrigins()
     * @see self::setAllowEmptyOrigins()
     */
    public function isOriginAllowed(?string &$allowed = null): bool
    {
        if (!$this->isListening) {
            return false;
        }

        $allowedOrigins = $this->incoming['accept.origins'] ?? null;

        if (!$allowedOrigins || $allowedOrigins === ['*']) {
            return true;
        }

        self::$request ??= Request::getInstance();

        if (self::$request->isSameOrigin(false)) {
            return true;
        }

        $origin = self::$request->getOrigin();

        if (!$origin) {
            return (bool) ($this->incoming['accept.empty.origin'] ?? false);
        }

        foreach ($allowedOrigins as $accept) {
            if ($accept === '*' || $origin === $accept) {
                $allowed = $accept;
                return true;
            }
        }

        return false;
    }

    /**
     * Free request payload data.
     *
     * @return void
     */
    public function free(): void 
    {
        $this->decoded = null;
        $this->payload = [];
        $this->rawPayload = null;
    }

    /**
     * Send the configured webhook request.
     *
     * This method builds the request, optionally signs the payload,
     * attaches it using the selected request field, and sends it
     * to the target webhook URL.
     *
     * If no field is provided:
     * - GET requests use `query`
     * - all other methods use `body`
     *
     * Supported request fields:
     * - `query`       Append payload as URL query parameters
     * - `body`        Send raw request body (for JSON or raw data)
     * - `form_params` Send URL-encoded form data
     * - `multipart`   Send multipart form data
     *
     * @param string|null $field Optional request field used to attach the payload.
     *
     * @return ResponseInterface<\Psr\Http\Message\ResponseInterface> Returns the HTTP response from the remote server.
     *
     * @throws InvalidArgumentException If an unsupported request field is provided.
     * @throws EncryptionException If payload signing fails.
     * @throws RequestException If the webhook URL is missing or the request fails.
     *
     * @example - Send a signed POST webhook:
     * ```php
     * $webhook = Webhook::request('https://example.com/webhook/users', 'POST')
     *     ->setPayload([
     *         'status' => 'OK',
     *         'user' => [
     *             'id' => 12345,
     *             'email' => 'user@example.com',
     *         ],
     *     ])
     *     ->setSecret('your-shared-secret')
     *     ->setAlgo('sha256')
     *     ->setSignatureHeaderName('X-Signature')
     *     ->setEvent('system.logs')
     *     ->sign();
     *
     * $response = $webhook->send();
     *
     * if ($response->getStatusCode() === 200) {
     *     echo "Webhook sent successfully.";
     * }
     * ```
     *
     * @example - Send GET request with query parameters:
     * ```php
     * $response = $webhook
     *     ->setMethod('GET')
     *     ->setPayload(['id' => 123])
     *     ->send('query');
     * ```
     */
    public function send(?string $field = null): ResponseInterface
    {
        if(!$this->url){
            throw new RequestException(
                'Webhook URL is not set. Please provide a valid URL before sending.'
            );
        }

        if(!$field){
            $field = ($this->method === 'GET') ? 'query' : 'body';
        }else{
            if (!in_array($field, ['query', 'body', 'form_params', 'multipart'], true)) {
                throw new InvalidArgumentException(
                    "Invalid request field '{$field}'. Use one of: 'query', 'body', 'form_params', or 'multipart'."
                );
            }
        }

        $this->payload['_hook']['webhook.internal'] = true;

        if($this->isSignable){
            $this->signPayload();
        }

        $options = [
            'timeout' => $this->timeout,
            'headers' => $this->getHeaders()
        ];

        if ($this->payload) {
            $options[$field] = $this->payload;
        }

        return (new Novio())->request($this->method, $this->url, $options);
    }

    /**
     * Set data to incoming detail.
     *
     * @param string $context The array key context.
     * @param array|string $value The value(s).
     * 
     * @return static Return instance of webhook class.
     */
    protected function setIncoming(string $context, array|string $value): self
    {
        if(!$this->isListening){
            return $this;
        }

        if(!is_array($value)){
            $this->incoming[$context][] = $value;
            return $this;
        }

        $this->incoming[$context] = array_merge(
            $this->incoming[$context], 
            $value
        );

        return $this;
    }

    /**
     * Signs the encoded payload and sets the signature header.
     *
     * This method is responsible for preparing and signing the payload using HMAC hashing.
     * 
     * It performs the following:
     * - Encodes the payload to string using `encode()`.
     * - Generates a secret if not already defined, using the app key and salt.
     * - Computes a signature using the chosen hashing algorithm and secret.
     * - Sets the signature in the request headers under the configured signature key.
     *
     * If you override this method:
     * - Ensure `$this->rawPayload` is set with a valid encoded string.
     * - Validate the payload before signing to prevent empty or invalid input.
     * - Manually set `$this->signature` and `$this->headers[$this->xSignature]`.
     *
     * @throws EncryptionException If the encoded payload is empty or invalid.
     */
    protected function signPayload(): void
    {
        if(!$this->xSignature){
            throw new RequestException(sprintf(
                'Missing signature header name. Set via %s::setSignatureHeaderName()',
                static::class
            ));
        }

        $this->rawPayload = $this->encode($this->payload);

        if (!$this->rawPayload) {
            throw new EncryptionException('Cannot sign: payload is empty or invalid.');
        }

        $this->secret ??= hash_hmac($this->algo, $this->salt, env('app.key'));
        $this->signature = hash_hmac($this->algo, $this->rawPayload, $this->secret);

        $this->headers[$this->xSignature] = $this->signature;
    }

    /**
     * Ensure the headers include 'Content-Type' and return all headers.
     *
     * @return array Return list of headers to send with the request.
     */
    protected function getHeaders(): array
    {
        if (!isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = 'application/json';
        }

        return $this->headers;
    }

    /**
     * Capture and decode the payload into the internal decoded buffer.
     *
     * @param array|null $data Optional pre-decoded payload to use.
     * 
     * @return bool Return true if decoding succeeded and the result is not empty.
     * @throws LogicException If the webhook context is not properly initialized via `listen()`.
     */
    private function doCapture(?array $data = null): bool
    {
        if ($this->incoming === []) {
            throw new LogicException('Cannot unpack: no signature context provided.');
        }

        if ($data === null) {
            $this->rawPayload = $this->encode([], true);
            $this->decoded = $this->decode($this->rawPayload);
        }else{
            $this->decoded = $data;
            $this->rawPayload = $this->encode($data);
        }

        return !empty($this->decoded);
    }

    /**
     * Check if the current request IP matches a defined list.
     *
     * This validates the request IP against either a whitelist or blacklist
     * context, depending on the provided key.
     *
     * @param string $context Configuration key (e.g. 'ip.whitelisted', 'ip.blacklisted').
     *
     * @return bool Returns true if the request IP matches an entry, false otherwise.
     */
    private function hasIPAddress(string $context = 'ip.blacklisted'): bool
    {
        $addresses = $this->incoming[$context] ?? [];

        if($addresses === []){
            return $context === 'ip.whitelisted';
        }

        foreach($addresses as $ip){
            if(IP::equals($ip)){
                return true;
            }
        }

        return false;
    }

    /**
     * Decode the given data as JSON if it's valid; otherwise return it as-is.
     *
     * @param mixed $data The data to decode.
     * @param bool  $asArray Whether to decode JSON as an associative array (true) or object (false).
     * 
     * @return mixed Return the decoded data if JSON, or original data if not valid JSON or decoding fails.
     */
    private function decode(mixed $data): mixed
    {
        if (is_string($data) && json_validate($data)) {
            try {
                return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {}
        }

        return $data;
    }

    /**
     * Convert the payload to a JSON string if it's an array.
     *
     * @return string|null JSON-encoded payload or the original string.
     * 
     * @throws EncryptionException If encoding to JSON fails.
     */
    private function encode(array $payload = [], bool $capture = false): ?string
    {
        $contentType = '';
        $method = null;

        if($payload === [] && $capture){
            self::$request ??= Request::getInstance();

            $payload = self::$request->getParsedBody();
            $method = self::$request->getMethod();
            $contentType = self::$request->getContentType();
        }

        if ($payload === []) {
            return null;
        }

        try {
            $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        
            if ($capture && ($method === 'GET' || str_contains($contentType, 'application/x-www-form-urlencoded'))) {
                $flags |= JSON_NUMERIC_CHECK;
            }

            return json_encode($payload, $flags);
        } catch (JsonException $e) {
            throw new EncryptionException('Failed to encode payload: ' . $e->getMessage(), 0, $e);
        }
    }
}