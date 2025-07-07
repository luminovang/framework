<?php 
/**
 * Luminova Framework Webhook Helper.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http;

use \Closure;
use \JsonException;
use \Luminova\Http\Request;
use \Luminova\Http\Client\Curl;
use \Luminova\Interface\{ResponseInterface, LazyInterface};
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
class Webhook implements LazyInterface
{
    /**
     * Configuration key used to define the webhook event name in the payload.
     * 
     * @var string EVENT_KEY
     */
    protected const EVENT_KEY = 'app.webhook.event';

    /**
     * Configuration key used to indicate whether the payload should be signed.
     *
     * @var string SIGN_KEY
     */
    protected const SIGN_KEY = 'app.webhook.signable';

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
     * @var string $xSignature
     */
    protected string $xSignature = 'X-Signature';

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
     * Create a new webhook instance.
     *
     * @param string $url The URL to which the webhook request will be sent.
     * @param string $method  The HTTP method to use for the request (default: POST).
     * @param int $timeout The request timeout in seconds (default: 0), for no timeout.
     */
    public function __construct(
        private string $url,
        protected string $method = 'POST',
        protected int $timeout = 0
    ) {
        $this->setMethod($this->method);
    }

    /**
     * Retrieve the webhook payload.
     *
     * This method returns either the full payload (including internal config)
     * or just the actual data section depending on the given flag.
     *
     * @param bool $withConfig Set to true to return the full payload including config metadata (default: `false`).
     *
     * @return array|string|null Return the payload array, raw data string, or null if empty.
     */
    public function getPayload(bool $withConfig = false): array|string|null
    {
        return $withConfig 
            ? $this->payload 
            : ($this->payload['_data'] ?? null);
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
     *     'X-App-Token' => 'abc123',
     *     'Accept' => 'application/json'
     * ]);
     * ```
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Set the data to be sent as the request payload or verify as incoming payload.
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
        $this->payload['_data'][$name] = $value;

        return $this;
    }

    /**
     * Set the secret key used for signing or verifying the payload.
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
     * Set the event name inside the webhook payload configuration.
     *
     * This stores the event name under the `_config` key of the payload,
     * using the predefined `EVENT_KEY` constant.
     *
     * @param string $name The event name to assign (e.g., 'user.created', 'payment.failed').
     * 
     * @return static Return instance of webhook class.
     */
    public function setEvent(string $name): self
    {
        $this->payload['_config'][self::EVENT_KEY] = $name;

        return $this;
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
     * @param string $algo The name of the hashing algorithm (default is 'sha256').
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
    public function setSignatureHeader(string $xSignature): self
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
     *      ->setSignatureHeader('X-MyApp-Signature'); // $request->header->get('X-Myapp-Signature')
     * ```
     *
     * > **Note:** If no secret is provided and none was set previously, a random secret will be generated.
     *       You can retrieve the key used by calling `getSecret()` after sending.
     */
    public function sign(?string $secret = null): self
    {
        $this->isSignable = true;
        $this->payload['_config'][self::SIGN_KEY] = 1;

        if($secret){
            $this->secret = $secret;
        }

        return $this;
    }

    /**
     * Listen and prepare a webhook for incoming verification.
     * 
     * This method initializes a new webhook instance with the provided signature and optional event name.
     * It should be called at the start of the webhook lifecycle to register the signature and context.
     *
     * @param string $signature The signature from the incoming request (e.g., `X-Signature` header).
     * @param string|null $event Optional event name expected in the payload.
     * 
     * @return static Return a new webhook instance configured for signature verification.
     *
     * @see isSignature()
     * @see unpack()
     *
     * @example - Example Usage: 
     * ```php
     * $webhook = Webhook::listen(
     *     $request->header->get('X-Signature'), // OR $_SERVER['HTTP_X_SIGNATURE']
     *     'event-name'
     * )
     * ->setAlgo('sha256')
     * ->setSecret('your-secret-key');
     * ```
     */
    public static function listen(string $signature, ?string $event = null): static
    {
        $instance = new static('');
        $instance->signature = $signature;
        $instance->incoming = ['onIncoming' => 1, 'event' => $event];

        return $instance;
    }

    /**
     * Unpack and validate the incoming webhook data.
     * 
     * This method verifies the signature of the payload and optionally runs a callback
     * with the decoded data if validation succeeds.
     *
     * @param Closure|null $onResponse Optional callback to handle the decoded payload.
     * @param array|null  $data Optional raw data to verify (defaults to request body).
     * 
     * @return mixed|null Return the decoded payload, or the result of the callback if provided.
     *
     * @throws EncryptionException If the signature check fails or the webhook is misconfigured.
     * @throws LogicException If called before initializing the webhook via `listen()`.
     *
     * @example - Example Usage: 
     * ```php
     * $webhook = Webhook::listen(...);
     * 
     * $webhook->unpack(function(mixed $payload): mixed {
     *     // Handle verified data
     *     return $payload;
     * });
     * 
     * // Or get the payload directly
     * $data = $webhook->unpack();
     * ```
     *
     * > You can also pass raw input using `(new Request)->getBody()`.
     */
    public function unpack(?Closure $onResponse = null, ?array $data = null): mixed
    {
        if (!$this->isSignature($data)) {
            if (!$this->decoded) {
                throw new RuntimeException(
                    'No request data to unpack.',
                    EncryptionException::NOT_ALLOWED
                );
            }

            throw new EncryptionException('Signature mismatch. Payload may have been tampered with.');
        }

        if ($onResponse instanceof Closure) {
            return $onResponse($this->decoded['_data'] ?? null);
        }

        return $this->decoded['_data'] ?? null;
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

        $event = $this->incoming['event'] 
            ?? $this->payload['_config'][self::EVENT_KEY] 
            ?? null;

        if (
            $event && (
                !isset($this->decoded['_config'][self::EVENT_KEY]) || 
                $this->decoded['_config'][self::EVENT_KEY] !== $event ||  $this->decoded !== $event
            )
        ) {
            return false;
        }

        if(!isset($this->decoded['_config'][self::SIGN_KEY])){
            return true;
        }

        if (!$this->signature || !$this->secret) {
            return false;
        }

        return $this->rawPayload && hash_equals(
            hash_hmac($this->algo, $this->rawPayload, $this->secret), 
            $this->signature
        );
    }

    /**
     * Send the configured webhook request to the target URL.
     *
     * Automatically signs the payload if signing is enabled.
     * The payload is attached using the specified field type based on the request method.
     *
     * @param string|null $field Optional request field to attach the payload:
     *      - 'query'         → For GET requests (appends data to the URL)
     *      - 'body'          → For raw POST/PUT/PATCH/DELETE payloads (e.g. JSON)
     *      - 'form_params'   → For application/x-www-form-urlencoded
     *      - 'multipart'     → For multipart/form-data
     *      If null, defaults to 'query' for GET and 'body' for other methods.
     * 
     * @return ResponseInterface<\Psr\Http\Message\ResponseInterface> Return the HTTP response returned from the request.
     * @throws EncryptionException If the payload is invalid or missing during the signing process.
     * @throws RequestException If error is encountered while sending webhook request.
     * @throws InvalidArgumentException If an invalid field name was provided.
     *
     * @example - Example Usage: 
     * ```php
     * // Basic POST webhook
     * $webhook->setPayload(['event' => 'ping'])->send();
     *
     * // GET request with query parameters
     * $webhook->setMethod('GET')
     *         ->setPayload(['id' => 123])
     *         ->send('query');
     * ```
     */
    public function send(?string $field = null): ResponseInterface
    {
        if(!$this->url){
            throw new RequestException(
                'Webhook URL is not set. Please provide a valid URL before sending.'
            );
        }

        if ($field && !in_array($field, ['query', 'body', 'form_params', 'multipart'])) {
            throw new InvalidArgumentException(
                "Invalid request field '{$field}'. Use one of: 'query', 'body', 'form_params', or 'multipart'."
            );
        }

        if($this->isSignable){
            $this->signPayload();
        }

        $field ??= ($this->method === 'GET') ? 'query' : 'body';

        $options = [
            'timeout' => $this->timeout,
            'headers' => $this->buildHeaders()
        ];

        if ($this->payload !== null) {
            $options[$field] = $this->payload;
        }

        return (new Curl())->request($this->method, $this->url, $options);
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
    private function buildHeaders(): array
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
        static $request = null;
        $contentType = '';
        $method = null;

        if($payload === [] && $capture){
            $request ??= new Request();

            $payload = $request->getBody();
            $method = $request->getMethod();
            $contentType = $request->getContentType();
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