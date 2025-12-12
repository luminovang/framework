<?php
/**
 * Luminova Framework base exception class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Base;

use \Throwable;
use \BackedEnum;
use \App\Config\AI as AIConfig;
use \Luminova\AI\Client\Ollama;
use \Luminova\Http\Client\Novio;
use \Psr\Http\Client\ClientInterface;
use \Psr\Http\Message\ResponseInterface;
use \Luminova\Interface\{AIClientInterface, LazyObjectInterface};
use \Luminova\Exceptions\{
    AIException, 
    RuntimeException, 
    LuminovaException, 
    BadMethodCallException, 
    InvalidArgumentException
};

abstract class AI implements LazyObjectInterface, AIClientInterface
{
    /**
     * AI client options.
     * 
     * @var array<string,mixed> $options
     */
    protected array $options = [];

    /**
     * HTTP client headers.
     * 
     * @var array<string,mixed> $headers
     */
    protected array $headers = [];

    /**
     * HTTP client instance.
     *
     * @var ClientInterface|Novio|null $http
     */
    protected ?ClientInterface $http = null;

    /**
     * Client API Base URL.
     *
     * @var string $baseUrl (e.g, `https://example.com/api/`)
     */
    protected string $baseUrl = '';

    /**
     * Client API Key.
     *
     * @var string $apiKey
     */
    protected ?string $apiKey = null;

    /**
     * Client API URI endpoint names and their URL path segments.
     * 
     * @var array<string,string> $endpoints
     */
    protected array $endpoints = [];

    /**
     * Default model used when none is specified per-request.
     *
     * @var string|null $model
     */
    protected ?string $model = null;

    /**
     * Default user ID used when none is specified per-request.
     *
     * @var string|int $user
     */
    protected string|int $user = '';

    /**
     * Is backed enum.
     *
     * @var array<string,bool> $isEnums
     */
    private static array $isEnums = [];

    /**
     * Create a new AI client instance with optional configuration.
     *
     * Sets the base URL, prepares default headers, and optionally initializes
     * the HTTP client. If an API key is provided, it is automatically added as
     * a Bearer token in the `Authorization` header.
     *
     * Custom headers override or extend the defaults (`Content-Type: application/json`).
     *
     * The HTTP client is only created when `$initHttpClient` is true, allowing
     * delayed initialization for advanced use cases.
     *
     * @param string|null $baseUrl Base API endpoint (e.g. https://example.com/api/).
     * @param string|null $apiKey API key for authenticated requests.
     * @param array<string,string> $headers Additional or overriding HTTP headers.
     * @param bool $initHttpClient Whether to initialize the HTTP client immediately.
     *
     * @see self::resolveConstructorArgs() For dynamic argument resolution.
     */
    public function __construct(
        ?string $baseUrl = null,
        ?string $apiKey = null,
        array $headers = [],
        bool $initHttpClient = true
    ) 
    {
        if ($baseUrl) {
            $this->setBaseUrl($baseUrl);
        }

        $default = ['Content-Type' => 'application/json'];

        if ($apiKey) {
            $default['Authorization'] = "Bearer {$apiKey}";
        }

        $this->headers = array_replace($default, $headers);
        $this->apiKey = $apiKey;

        if (!$initHttpClient) {
            return;
        }

        $this->http = new Novio(['headers' => $this->headers]);
    }

    /**
     * Dynamically set a request option using a fluent method call.
     *
     * Any undefined method invoked on the instance will be treated as an
     * option name and stored in the default request options.
     *
     * @param string $name The option name being set.
     * @param array $arguments Method arguments where the first value is used as the option value. 
     *                      If no argument is provided, the value defaults to `true`.
     *
     * @return static Returns the current instance.
     *
     * @example - Example:
     * ```php
     * $client->temperature(0.2)
     *    ->top_p(0.9)
     *    ->max_tokens(500);
     * ```
     *
     * Internally this produces:
     * ```php
     * [
     *     'temperature' => 0.2,
     *     'top_p' => 0.9,
     *     'max_tokens' => 500
     * ]
     * ```
     */
    public function __call(string $name, array $arguments): self 
    {
        return $this->option($name, $arguments[0] ?? true);
    }

    /**
     * Dynamically proxy static method calls to the underlying AI client instance.
     *
     * This allows calling client methods statically while internally resolving
     * and reusing a configured client instance.
     *
     * @param string $method The method name being called.
     * @param array  $arguments Arguments passed to the method.
     *
     * @return mixed Return result of called method.
     * @throws BadMethodCallException If the method does not exist on the client.
     *
     * @example - Example:
     * ```php
     * Ollama::chat($input);
     * OpenAI::chat($input);
     * Anthropic::chat($input);
     * ```
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $client = static::newClient();

        if (!method_exists($client, $method)) {
            throw new BadMethodCallException(
                sprintf('Method "%s" does not exist on client "%s".', $method, $client::class)
            );
        }

        return $client->{$method}(...$arguments);
    }

    /**
     * Retrieve all client HTTP headers.
     *
     * @return array<string,mixed> Return an associative array of HTTP headers.
     */
    public function getHeaders(): array 
    {
        return $this->http->getConfig('headers') ?: $this->headers;
    }

    /**
     * Set AI client specific API request header.
     *
     * @param string $name The header name.
     * @param mixed $value The header value.
     * 
     * @return static Returns the current instance.
     */
    public function header(string $name, mixed $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Sets AI client specific API request headers at once.
     *
     * @param array<string,mixed> $headers The array headers key-value.
     * 
     * @return static Returns the current instance.
     * 
     * > **Note:**
     * > This replaces the old headers with new value if already exists
     */
    public function headers(array $headers): self
    {
        $this->headers = array_replace($this->headers, $headers);
        return $this;
    }

    /**
     * Explicitly set a request option.
     *
     * This method provides a direct and IDE-friendly way to define
     * request parameters when the option name is dynamic or when
     * avoiding the magic method.
     *
     * @param string $name The option name.
     * @param mixed $value The option value.
     *
     * @return static Returns the current instance.
     *
     * @example - Example:
     * ```php
     * $client->option('temperature', 0.2)
     *    ->option('max_tokens', 500);
     * ```
     */
    public function option(string $name, mixed $value): self 
    {
        $this->options[$name] = $this->isEnum($value)
            ? $value->value 
            : $value;

        return $this;
    }

    /**
     * Retrieve all AI request options.
     *
     * @return array<string,mixed> Returns the current AI client options.
     *
     * @example - Example:
     * ```php
     * $client->options()
     * ```
     */
    public function options(): array 
    {
        return $this->options;
    }

    /**
     * Clear all AI client options.
     * 
     * This ensures options are cleared before reusing same object.
     *
     * @return static Returns the current instance.
     * @see self::clearHeaders() To clear options.
     *
     * @example - Example:
     * ```php
     * $client->option('temperature', 0.2)
     *    ->clearOptions()
     *    ->option('max_tokens', 500);
     * ```
     */
    public function clearOptions(): self
    {
        $this->options = [
            'user' => '',
            'model' => $this->model ?? 'luminova'
        ];

        return $this;
    }

    /**
     * Clear all custom API request headers.
     * 
     * This ensures headers are cleared before reusing same object.
     *
     * @return static Returns the current instance.
     * @see self::clearOptions() To clear options.
     *
     * @example - Example:
     * ```php
     * $client->header('X-Example-Com', 'Example')
     *    ->clearHeaders()
     *    ->header('X-Example-Org', 'Example');
     * ```
     */
    public function clearHeaders(): self
    {
        $this->headers = [];
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseUrl(string $url): static
    {
        $this->baseUrl = rtrim($url, '/') . '/';
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setModel(object|string $model): static
    {
        if ($this->isEnum($model)) {
            $this->model = $model->value;
        } elseif (is_string($model)) {
            $this->model = $model;
        } else {
            throw new InvalidArgumentException('Model must be a string or a BackedEnum instance.');
        }

        return $this->option('model', $this->model);
    }

    /**
     * {@inheritdoc}
     */
    public function user(string|int $user): self
    {
        $this->user = $user;
        return $this->option('user', $user);
    }
    
    /**
     * {@inheritdoc}
     *
     * > Delegates to `message()` for OpenAI.
     */
    public function generate(string $prompt, array $options = []): array
    {
        return $this->message($prompt, $options);
    }

    /**
     * {@inheritdoc}
     *
     * > Delegates to `chat()` with the prompt wrapped in a user message.
     */
    public function message(string $prompt, array $options = []): array
    {
        return $this->chat(['role' => 'user', 'content' => $prompt], $options);
    }

    /**
     * {@inheritdoc}
     */
    public function models(): array
    {
        return $this->__models();
    }

    /**
     * {@inheritdoc}
     */
    public function model(string $name): array
    {
       return $this->__models($name);
    }

    /**
     * {@inheritdoc}
     */
    public function image(string $prompt, array $options = []): array|bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function embed(string|array $input, array $options = []): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function speech(string $text, array $options = []): string|bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function audio(string $prompt, string $filename, array $options = []): string|bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function fineTune(string $model, string $dataset, array $options = []): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function fineTuneDataset(array $dataset, array $options = []): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function fineTuneStatus(string $jobId): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function webSearch(string $query, array $options = []): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     * 
     * @see self::newClient()
     */
    public static function resolveConstructorArgs(AIConfig $config): array
    {
        return [];
    }

    /**
     * Create or retrieve a cached AI client instance for the current class.
     *
     * This method ensures:
     * - A single client instance is reused per class (simple static caching).
     * - Configuration is loaded once and shared.
     * - Correct constructor arguments are applied per provider.
     * - Default model is set automatically if defined.
     *
     * Implemented clients:
     * - Ollama
     * - Anthropic
     * - OpenAI
     * 
     * @return AIClientInterface Return created or shared object.
     * @see self::__callStatic()
     *
     * @example - Example:
     * ```php
     * $client = OpenAI::newClient();
     * 
     * $response = $client->chat([
     *     'model' => 'gpt-4',
     *     'messages' => [...]
     * ]);
     * ```
     * @return AIClientInterface Configured client instance.
     */
    protected static final function newClient(): AIClientInterface
    {
        static $config;
        static $clients = [];

        $class = static::class;

        if (isset($clients[$class])) {
            return $clients[$class];
        }

        $config ??= new AIConfig();
        $client = new static(...static::resolveConstructorArgs($config, $class));

        if ($config->model) {
            $client->setModel($config->model);
        }

        return $clients[$class] = $client;
    }

    /**
     * Check if a given value is a PHP enum (BackedEnum) or a Luminova AI Model enum.
     *
     * This method supports PHP 8.1+ enums and the custom Luminova\Model enum.
     * Returns false for strings or when running on PHP < 8.1.
     *
     * @param string|object $value The value to check.
     *
     * @return bool Return true if $value is an enum instance, false otherwise.
     */
    protected function isEnum(string|object $value): bool
    {
        if (PHP_VERSION_ID < 80100 || !is_object($value)) {
            return false;
        }

        self::$isEnums['custom'] ??= class_exists(BackedEnum::class);

        if (self::$isEnums['custom'] && ($value instanceof BackedEnum)) {
            return true;
        }

        self::$isEnums['model'] ??= class_exists(\Luminova\AI\Model::class);
       
        return (self::$isEnums['model'] && ($value instanceof \Luminova\AI\Model));
    }

    /**
     * Convert a structured dataset array into Modelfile directive lines.
     *
     * Recognizes three top-level keys:
     * - `parameters` → `PARAMETER name value` lines
     * - `templates`  → `TEMPLATE value` lines
     * - `messages`   → `MESSAGE role content` lines
     *
     * > Any other key is written as `key value` verbatim.
     * Example: `LICENSE => """ MIT """`
     *
     * @param array<string,mixed> $dataset Structured dataset to serialize.
     *
     * @return string Modelfile content string (no leading/trailing whitespace).
     * @throws InvalidArgumentException if invalid modelfile dataset was provided.
     */
    public static function toModelFile(array $dataset): string
    {
        $params  = '';
        $templates = '';
        $messages  = '';

        if(array_is_list($dataset)){
            throw new InvalidArgumentException('Invalid array list, expected associative array.');
        }

        foreach ($dataset as $key => $body) {
            if (!is_array($body)) {
                $messages .= "\n{$key} {$body}";
                continue;
            }

            foreach ($body as $name => $value) {
                match ($key) {
                    'parameters' => $params .= "\nPARAMETER {$name} {$value}",
                    'templates'  => $templates .= "\nTEMPLATE {$value}",
                    'messages'   => $messages  .= match (true) {
                        isset($value['prompt'], $value['completion']) =>
                            "\nMESSAGE User: {$value['prompt']}\nMESSAGE Assistant: {$value['completion']}",
                        isset($value['prompt'])     =>
                            "\nMESSAGE User: {$value['prompt']}",
                        isset($value['completion']) =>
                            "\nMESSAGE Assistant: {$value['completion']}",
                        isset($value['role'])       =>
                            "\nMESSAGE " . ucfirst($value['role']) . ': ' . ($value['content'] ?? ''),
                        default =>
                            "\nMESSAGE " . ucfirst((string) $name) . ": {$value}",
                    },
                    default => $messages .= "\n{$name}: {$value}",
                };
            }
        }

        if ($templates === '') {
            $templates = 'TEMPLATE """
            {{ if .System }}System: {{ .System }}{{ end }}
            {{ if .Prompt }}User: {{ .Prompt }}{{ end }}
            Assistant:"""';
        }

        return trim("{$params}\n{$templates}\n{$messages}");
    }

    /**
     * Send HTTP request to AI client API,
     * 
     * @param string $method The HTTP request method.
     * @param string $url The full API request URL.
     * @param array<string,mixed> $finalOptions The HTTP client options built for AI client.
     * 
     * @return array Return http request API response array.
     * @throws Throwable if error occur.
     * 
     * > **Note:**
     * > This uses {@see self::toResponse()}, to normalize API response to array.
     */
    protected function send(string $method, string $url, array $finalOptions): array 
    {
        try {
            return $this->toResponse($this->http->request($method, $url, $finalOptions));
        } catch (Throwable $e) {
            $this->eHandler($e);
        }

        return [];
    }

    /**
     * Build the full URL for a named endpoint.
     *
     * @param string $name The endpoint URI name from `$this->endpoints`.
     * @param string $suffix Optional URL path suffix appended after endpoint segment (e.g, `foo/bar`).
     *
     * @return string Fully-qualified endpoint URL.
     * @throws RuntimeException If the endpoint name is not registered.
     * 
     * @example - Example:
     * ```php
     * $url = $this->endpoint('responses');
     * // https://example.com/v1/api/responses
     * ```
     * 
     * @example - With Suffix:
     * ```php
     * $url = $this->endpoint('models', 'gpt4');
     * // https://example.com/v1/api/models/gpt4
     * ```
     */
    protected function endpoint(string $name, string $suffix = ''): string
    {
        if (!isset($this->endpoints[$name])) {
            throw new RuntimeException("Endpoint \"{$name}\" is not implemented.");
        }

        $suffix = ltrim($suffix, '/');

        return $this->baseUrl . $this->endpoints[$name] . '/' . $suffix;
    }

    /**
     * Decode the HTTP response body into an associative array.
     *
     * Supports both standard JSON and newline-delimited JSON (NDJSON) streams.
     * Automatically strips SSE-style `data:` prefixes and ignores empty lines.
     * Invalid JSON lines are skipped, or optionally collected as raw entries.
     *
     * @param ResponseInterface $res The raw HTTP response.
     * @param bool $parseRaw Whether to capture non-JSON lines under a `__raw` key.
     *
     * @return array The normalized response data.
     *
     * @throws JsonException If valid JSON decoding fails.
     */
    protected function toResponse(ResponseInterface $res, bool $parseRaw = true): array
    {
        $body = $res->getBody()->getContents();

        if ($body && json_validate($body)) {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } else {
            $data = [];

            foreach (explode("\n", $body) as $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                if (str_starts_with($line, 'data:')) {
                    $line = trim(substr($line, 5));
                }

                if (!json_validate($line)) {
                    if($parseRaw){
                        $data['__raw'][] = $line;
                    }

                    continue;
                }

                $data[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            }
        }

        if (isset($data['error']) || (isset($data[0]['error']))) {
            $error = $data['error'] ?? $data[0]['error'] ?? null;
            $message = is_array($error)
                ? ($error['message'] ?? null)
                : $error;

            return [
                'error' => $res->getStatusCode(),
                'message' => $message ?? 'Request could not be completed or invalid API response.',
                'details' => $data,
            ];
        }

        return $data;
    }

    /**
     * Parse the base parameter array for an AI request.
     *
     * Merges sensible `$this->options` with caller-supplied `$options`.
     *
     * @param array $options Caller-supplied options.
     *
     * @return void
     */
    protected function parseOptions(array $options = []): void
    {
        if($this->user && !isset($options['user'])){
            $options['user'] = $this->user;
        }

        if (!isset($options['model'])){
            $options['model'] = $this->model ?? 'luminova';
        } elseif($this->isEnum($options['model'])){
            $options['model'] = $options['model']->value;
        }

        $this->options = array_replace($this->options, $options);
    }

    /**
     * Normalize and re-throw exceptions from API calls.
     *
     * `LuminovaException`, other classes are re-thrown as `AIException`.
     * @param Throwable $e The caught exception.
     *
     * @return never
     * @throws LuminovaException When `$e` is already an `LuminovaException`.
     * @throws AIException For all other throwables.
     */
    protected function eHandler(Throwable $e): never
    {
        if ($e instanceof LuminovaException) {
            throw $e;
        }

        throw new AIException($e->getMessage(), $e->getCode(), $e);
    }

    /**
     * Retrieve available models or details for a specific model.
     *
     * This method adapts the request based on the AI client implementation.
     * Standard providers (such as OpenAI or compatible APIs) use a `GET`
     * request to list models or retrieve a specific model by name.
     *
     * When `$name` is `null`, a list of available models is returned.
     * When `$name` is provided, details for the specified model are returned.
     *
     * @param string|null $name Optional model name. If provided, the method
     *                          returns details for that specific model.
     *
     * @return array Returns an array of models when `$name` is `null`,
     *               otherwise returns the model details.
     * @throws AIException if error occur.
     * @see self::model()
     * @see self::models()
     *
     * @example - List available models:
     * ```php
     * $models = $client->__models();
     * 
     * // Sames As
     * $models = $client->models();
     * ```
     *
     * @example - Get details for a specific model:
     * ```php
     * $model = $client->__models('gpt-4.1-mini');
     * 
     * // Sames As
     * $model = $client->model('gpt-4.1-mini');
     * ```
     */
    protected function __models(?string $name = null): array
    {
        $method = 'GET';
        $isOllama = ($this instanceof Ollama);

        if ($name === null) {
            $url = $this->endpoint('models');
        } elseif ($isOllama) {
            $method = 'POST';
            $url = $this->endpoint('show');
        } else {
            $url = $this->endpoint('models', "/{$name}");
        }

        $res = $this->send(
            $method, 
            $url, 
            $isOllama ? ['body' => ['name' => $name]] : []
        );

        if ($isOllama || $name !== null) {
            if ($name === null) {
                return $res['models'] ?? [];
            }

            return $res;
        }

        return (array) ($res['data'] ?? $res);
    }
}