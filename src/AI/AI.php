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
namespace Luminova\AI;

use \Throwable;
use \Luminova\AI\Model;
use \App\Config\AI as AIConfig;
use \Luminova\Interface\AIClientInterface;
use \Luminova\AI\Client\{OpenAI, Ollama, Anthropic};
use \Luminova\Exceptions\{LuminovaException, AIException, RuntimeException};

/**
 * Central AI manager that wraps any `AIClientInterface` implementation and
 * forwards method calls to the active client.
 *
 * Supports both instance-based and static usage patterns, lazy client
 * instantiation from `App\Config\AI`, and a runtime client registry so
 * multiple engines can be maintained side-by-side.
 *
 * @mixin \Luminova\AI\Client\OpenAI
 * @mixin \Luminova\AI\Client\Ollama
 * @mixin \Luminova\AI\Client\Anthropic
 * @mixin \Luminova\Interface\AIClientInterface
 *
 * @method static Ollama Ollama(?string $baseurl = null, ?string $apiKey = null)
 * @method static OpenAI Openai(string $apiKey, ?string $organization = null, ?string $project = null)
 * @method static Anthropic Anthropic(string $apiKey, ?string $version = null, ?string $betaFeatures = null)
 * 
 * @link https://luminova.ng/docs/0.0.80/ai-client/manager
 */
final class AI
{
    /**
     * Shared singleton instance.
     *
     * @var self|null $instance
     */
    private static ?self $instance = null;

    /**
     * Registered client class names or instances.
     *
     * @var array<string,class-string<AIClientInterface>|AIClientInterface> $clients
     */
    private static array $clients = [
        'openai'    => OpenAI::class,
        'ollama'    => Ollama::class,
        'anthropic' => Anthropic::class
    ];

    /**
     * Create a new AI manager instance.
     *
     * If no client is supplied, the default client configured in
     * `App\Config\AI::$handler` is instantiated automatically.
     *
     * @param AIClientInterface|null $client Optional custom client instance.
     *
     * @example - Using the application default client:
     * ```php
     * $ai = new AI();
     * $reply = $ai->message('Tell me a joke!');
     * ```
     *
     * @example - Injecting an Ollama client explicitly:
     * ```php
     * use Luminova\AI\Client\Ollama;
     *
     * $ai = new AI(new Ollama('http://localhost:11434'));
     * $reply = $ai->message('Explain quantum computing in plain language.');
     * ```
     */
    public function __construct(private ?AIClientInterface $client = null)
    {
        if (!$this->client instanceof AIClientInterface){
            $this->client = self::newClient();
        }
    }

    /**
     * Get (or create) the shared AI singleton instance.
     *
     * On first call, a new instance is created using the application's
     * default client. Subsequent calls return the same instance. 
     * 
     * Passing a `$client` on any call after the first will replace the active client
     * without destroying the singleton.
     *
     * @param AIClientInterface|null $client Optional client to set or replace.
     *
     * @return self Return singleton object of AI class.
     *
     * @example - Example:
     * 
     * ```php
     * $reply = AI::getInstance()->message('Write a haiku about the ocean.');
     * ```
     *
     * @example - Swapping clients on the singleton:
     * 
     * ```php
     * use Luminova\AI\Client\Ollama;
     *
     * AI::getInstance(new Ollama())->message('Hello from Ollama!');
     * ```
     */
    public static function getInstance(?AIClientInterface $client = null): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self($client);
        } elseif ($client instanceof AIClientInterface) {
            self::$instance->setClient($client);
        }

        return self::$instance;
    }

    /**
     * Get the currently active AI client.
     *
     * @return AIClientInterface Return instance of client class.
     *
     * @example - Example:
     * 
     * ```php
     * $client = AI::getInstance()->getClient();
     * echo get_class($client); // Luminova\AI\Client\OpenAI
     * ```
     */
    public function getClient(): AIClientInterface
    {
        return $this->client;
    }

    /**
     * Replace the active AI client.
     *
     * Allows switching engines at runtime without creating a new `AI` instance.
     *
     * @param AIClientInterface $client The new client to use.
     *
     * @return self Return instance of AI class.
     *
     * @example - Example:
     * 
     * ```php
     * use Luminova\AI\Client\Ollama;
     *
     * $ai = new AI();
     * $ai->setClient(new Ollama('http://localhost:11434'));
     *
     * $reply = $ai->message('Now powered by Ollama!');
     * ```
     */
    public function setClient(AIClientInterface $client): self
    {
        $this->client = $client;
        return $this;
    }

    /**
     * Set the default model for all subsequent AI requests.
     *
     * @param BackedEnum|Model<BackedEnum>|string $model AI model string name or enum model 
     *          (e.g, `Model::GPT_4_1_MINI` or `gpt-4.1-mini`).
     * 
     * @return self Return instance of AI class.
     */
    public function setModel(object|string $model): self
    {
        $this->client->setModel($model);
        return $this;
    }

    /**
     * Register a named client in the global client registry.
     *
     * Accepts either a pre-built instance or a fully-qualified class name.
     * Once registered, the client can be retrieved via static method calls
     * or `AI::client()`.
     *
     * @param string $name Case-insensitive registry key (e.g. `'openai'`).
     * @param AIClientInterface|class-string<AIClientInterface> $client Instance or class name implementing AIClientInterface.
     *
     * @return void
     * @throws InvalidArgumentException If the client does not implement the required interface.
     *
     * @example - Registering a live instance:
     * 
     * ```php
     * AI::register('openai', new OpenAI(...));
     * AI::register('ollama', new Ollama(...));
     * AI::register('anthropic', new Anthropic(...));
     *
     * AI::Openai()->message('Hello from OpenAI!');
     * AI::Ollama()->message('Hello from Ollama!');
     * AI::Anthropic()->message('Hello from Anthropic!');
     * ```
     *
     * @example - Registering a class name (instantiated on first use):
     * 
     * ```php
     * AI::register('myclient', MyCustomProvider::class);
     * AI::client('myclient')->message('Hello!');
     * 
     * AI::Myclient()->message('Hello from Myclient!');
     * ```
     */
    public static function register(string $name, AIClientInterface|string $client): void
    {
        if (
            !$client instanceof AIClientInterface &&
            !is_a($client, AIClientInterface::class, true)
        ) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid AI client: expected instance or class implementing "%s", got "%s".',
                AIClientInterface::class,
                is_object($client) ? $client::class : (string) $client
            ));
        }

        self::$clients[strtolower($name)] = $client;
    }

    /**
     * Retrieve a client instance from the registry by name.
     *
     * If no name is given, the application's default client is returned.
     * If the client entry is a class name string, it is instantiated on
     * first access and the instance is cached for subsequent calls.
     *
     * @param string|null $name Registry key (case-insensitive), or `null` for the default.
     *
     * @return AIClientInterface Return client class object.
     * @throws RuntimeException If the requested client is not registered.
     *
     * @example - Example:
     * ```php
     * $openai = AI::client('openai');
     * $ollama = AI::client('ollama');
     *
     * $openai->message('Hello from OpenAI!');
     * $ollama->message('Hello from Ollama!');
     * ```
     */
    public static function client(?string $name = null): AIClientInterface
    {
        return self::newClient($name);
    }

    /**
     * Return all registered client entries.
     *
     * Values are either class-name strings (not yet instantiated) or live
     * `AIClientInterface` objects.
     *
     * @return array<string,class-string<AIClientInterface>|AIClientInterface> Return array of registered clients.
     *
     * @example - Example:
     * ```php
     * foreach (AI::clients() as $name => $client) {
     *     echo $name . ': ' . (is_string($client) ? $client : get_class($client));
     * }
     * ```
     */
    public static function clients(): array
    {
        return self::$clients;
    }

    /**
     * Compute the cosine similarity between two equal-length embedding vectors.
     *
     * Returns a value in the range `[-1, 1]` where `1` means identical direction,
     * `0` means orthogonal, and `-1` means opposite direction. Useful for
     * comparing embeddings produced by `AIClientInterface::embed()`.
     *
     * @param float[] $a First embedding vector.
     * @param float[] $b Second embedding vector (must be the same length as `$a`).
     *
     * @return float Cosine similarity score.
     *
     * @example - Example:
     * 
     * ```php
     * $vectors = $ai->embed(['cat', 'kitten']);
     * $score = AI::compareCosineVector($vectors[0], $vectors[1]);
     * // $score → ~0.94 (very similar)
     * ```
     */
    public static function compareCosineVector(array $a, array $b): float
    {
        $dot   = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $v) {
            $dot   += $v * $b[$i];
            $normA += $v * $v;
            $normB += $b[$i] * $b[$i];
        }

        $denominator = sqrt($normA) * sqrt($normB);

        if ($denominator === 0.0) {
            return 0.0;
        }

        return $dot / $denominator;
    }

    /**
     * Forward instance method calls to the underlying AI client.
     *
     * @param string $method Provider method name.
     * @param array $arguments Method arguments.
     *
     * @return mixed Return result.
     * @throws Throwable If the client throws a non-application exception.
     *
     * @example - Example:
     * 
     * ```php
     * $ai= new AI();
     * $result = $ai->message('Describe a black hole in one paragraph.');
     * ```
     */
    public function __call(string $method, array $arguments): mixed
    {
        try {
            return $this->client->{$method}(...$arguments);
        } catch (Throwable $e) {
            if ($e instanceof LuminovaException) {
                throw $e;
            }

            throw new AIException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Forward static method calls to the active client or registry.
     *
     * @param string $method Provider method name, or a registered client key.
     * @param array $arguments Method arguments, or constructor arguments for the client.
     *
     * @return mixed Provider instance (if `$method` is a registry key) or the method result.
     * @throws Throwable If the client throws a non-application exception.
     *
     * @example - Calling a client method statically:
     * ```php
     * $reply = AI::message('Write a short story about Mars.');
     * ```
     *
     * @example - Accessing a named client via magic static call:
     * ```php
     * $reply = AI::Openai($apiKey)->message('Hello from OpenAI!');
     * $reply = AI::Ollama()->message('Hello from Ollama!');
     * ```
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        if (isset(self::$clients[strtolower($method)])) {
            return self::newClient($method, $arguments);
        }

        try{
            return self::newClient()->{$method}(...$arguments);
        } catch (Throwable $e) {
            if ($e instanceof LuminovaException) {
                throw $e;
            }

            throw new AIException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Resolve or instantiate a client by registry name.
     *
     * Reads constructor arguments from `App\Config\AI` when no explicit `$args`
     * are given. Caches the resulting instance back into `self::$clients` so
     * subsequent calls skip re-instantiation.
     *
     * @param string|null $name Registry key, or `null` to use the configured default.
     * @param array $args Explicit constructor arguments (overrides config).
     *
     * @return AIClientInterface
     * @throws RuntimeException If the client name is not registered.
     */
    private static function newClient(?string $name = null, array $args = []): AIClientInterface
    {
        static $config;

        $config ??= new AIConfig();
        $name = strtolower($name ?? ($config->handler ?: 'openai'));

        /**
         * @var class-string<AIClientInterface>|AIClientInterface $class
         */
        $class = self::$clients[$name] ?? null;

        if ($class instanceof AIClientInterface) {
            return $class;
        }

        if (!$class) {
            throw new RuntimeException(sprintf(
                'AI client "%s" is not registered. Add it via AI::register() or set App\Config\AI::$handler = "%s".',
                $name,
                $name
            ));
        }

        if ($args === []) {
            $args = $class::resolveConstructorArgs($config);
        }

        self::$clients[$name] = new $class(...$args);

        if($config->model){
            self::$clients[$name]->setModel($config->model);
        }

        return self::$clients[$name];
    }
}