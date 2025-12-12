<?php
declare(strict_types=1);
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
use \App\Config\AI;
use Luminova\AI\Model;
use Luminova\Interface\AIAgentInterface;
use Luminova\AI\Agents\{OpenAI, Ollama, Anthropic};
use Luminova\Exceptions\{LuminovaException, AIAgentException, RuntimeException, InvalidArgumentException};

/**
 * Central AI manager that wraps any `AIAgentInterface` implementation and
 * forwards method calls to the active agent.
 *
 * Supports both instance-based and static usage patterns, lazy agent
 * instantiation from `App\Config\AI`, and a runtime agent registry so
 * multiple engines can be maintained side-by-side.
 *
 * @mixin \Luminova\AI\Agents\OpenAI
 * @mixin \Luminova\AI\Agents\Ollama
 * @mixin \Luminova\AI\Agents\Anthropic
 * @mixin \Luminova\Interface\AIAgentInterface
 *
 * @method static Ollama<\Luminova\Base\AIAgent> Ollama(?string $baseUrl = null, ?string $apiKey = null)
 * @method static OpenAI<\Luminova\Base\AIAgent> OpenAI(?string $baseUrl = null, ?string $apiKey = null, ?string $organization = null, ?string $project = null)
 * @method static Anthropic<\Luminova\Base\AIAgent> Anthropic(?string $baseUrl = null, ?string $apiKey = null, ?string $version = null, ?string $betaFeatures = null)
 * 
 * @link https://luminova.ng/docs/0.0.80/ai-agent/manager
 */
final class Agent
{
    /**
     * Shared singleton instance.
     *
     * @var self|null $instance
     */
    private static ?self $instance = null;

    /**
     * Registered agent class names or instances.
     *
     * @var array<string,class-string<AIAgentInterface>|AIAgentInterface> $agents
     */
    private static array $agents = [
        'openai'    => OpenAI::class,
        'ollama'    => Ollama::class,
        'anthropic' => Anthropic::class
    ];

    /**
     * Create a new AI manager instance.
     *
     * If no agent is supplied, the default agent configured in
     * `App\Config\AI::$handler` is instantiated automatically.
     *
     * @param AIAgentInterface|null $agent Optional custom agent instance.
     *
     * @example - Using the application default agent:
     * ```php
     * $ai = new AI();
     * $reply = $ai->message('Tell me a joke!');
     * ```
     *
     * @example - Injecting an Ollama agent explicitly:
     * ```php
     * use Luminova\AI\Agents\Ollama;
     *
     * $ai = new AI(new Ollama('http://localhost:11434'));
     * $reply = $ai->message('Explain quantum computing in plain language.');
     * ```
     */
    public function __construct(private ?AIAgentInterface $agent = null)
    {
        if (!$this->agent instanceof AIAgentInterface){
            $this->agent = self::newAgent();
        }
    }

    /**
     * Get (or create) the shared AI singleton instance.
     *
     * On first call, a new instance is created using the application's
     * default agent. Subsequent calls return the same instance. 
     * 
     * Passing a `$agent` on any call after the first will replace the active agent
     * without destroying the singleton.
     *
     * @param AIAgentInterface|null $agent Optional agent agent to set or replace.
     *
     * @return self Return singleton object of AI class.
     *
     * @example - Example:
     * 
     * ```php
     * $reply = Agent::create()->message('Write a haiku about the ocean.');
     * ```
     *
     * @example - Swapping agents on the singleton:
     * 
     * ```php
     * use Luminova\AI\Agents\Ollama;
     *
     * Agent::create(new Ollama())->message('Hello from Ollama!');
     * ```
     */
    public static function create(?AIAgentInterface $agent = null): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self($agent);
        } elseif ($agent instanceof AIAgentInterface) {
            self::$instance->setAgent($agent);
        }

        return self::$instance;
    }

    /**
     * Get the currently active AI agent.
     *
     * @return AIAgentInterface Return instance of agent class.
     *
     * @example - Example:
     * 
     * ```php
     * $agent = Agent::create()->getAgent();
     * echo get_class($agent); // Luminova\AI\Agents\OpenAI
     * ```
     */
    public function getAgent(): AIAgentInterface
    {
        return $this->agent;
    }

    /**
     * Replace the active AI agent.
     *
     * Allows switching engines at runtime without creating a new `AI` instance.
     *
     * @param AIAgentInterface $agent The new AI agent to use.
     *
     * @return self Return instance of AI class.
     *
     * @example - Example:
     * 
     * ```php
     * use Luminova\AI\Agents\Ollama;
     *
     * $ai = new AI();
     * $ai->setAgent(new Ollama('http://localhost:11434'));
     *
     * $reply = $ai->message('Now powered by Ollama!');
     * ```
     */
    public function setAgent(AIAgentInterface $agent): self
    {
        $this->agent = $agent;
        return $this;
    }

    /**
     * Set the default model for all subsequent AI requests.
     *
     * @param \BackedEnum|Model<\BackedEnum>|string $model AI model string name or enum model 
     *          (e.g, `Model::GPT_4_1_MINI` or `gpt-4.1-mini`).
     * 
     * @return self Return instance of AI class.
     */
    public function setModel(object|string $model): self
    {
        $this->agent->setModel($model);
        return $this;
    }

    /**
     * Register a named agent in the global agents registry.
     *
     * Accepts either a pre-built instance or a fully-qualified class name.
     * Once registered, the agent can be retrieved via static method calls
     * or `Agent::with()`.
     *
     * @param string $name Case-insensitive registry key (e.g. `'openai'`).
     * @param AIAgentInterface|class-string<AIAgentInterface> $agent Instance or class name implementing AIAgentInterface.
     *
     * @return void
     * @throws InvalidArgumentException If the agent does not implement the required interface.
     *
     * @example - Registering a live instance:
     * 
     * ```php
     * Agent::register('openai', new OpenAI(...));
     * Agent::register('ollama', new Ollama(...));
     * Agent::register('anthropic', new Anthropic(...));
     *
     * Agent::Openai()->message('Hello from OpenAI!');
     * Agent::Ollama()->message('Hello from Ollama!');
     * Agent::Anthropic()->message('Hello from Anthropic!');
     * ```
     *
     * @example - Registering a class name (instantiated on first use):
     * 
     * ```php
     * Agent::register('myAgent', MyCustomProvider::class);
     * Agent::with('myAgent')->message('Hello!');
     * 
     * Agent::MyAgent()->message('Hello from my agent!');
     * ```
     */
    public static function register(string $name, AIAgentInterface|string $agent): void
    {
        if (
            !$agent instanceof AIAgentInterface &&
            !is_a($agent, AIAgentInterface::class, true)
        ) {
            throw new InvalidArgumentException(sprintf(
                'Invalid AI agent: expected instance or class implementing "%s", got "%s".',
                AIAgentInterface::class,
                is_object($agent) ? $agent::class : (string) $agent
            ));
        }

        self::$agents[strtolower($name)] = $agent;
    }

    /**
     * Retrieve an AI agent instance from the registry by name.
     *
     * If no name is given, the application's default agent is returned.
     * If the agent registry is a class name string, it is instantiated on
     * first access and the instance is cached for subsequent calls.
     *
     * @param string|null $name Registry key (case-insensitive), or `null` for the default.
     *
     * @return AIAgentInterface Return AI agent class object.
     * @throws RuntimeException If the requested agent is not registered.
     *
     * @example - Example:
     * ```php
     * $openai = Agent::with('openai');
     * $ollama = Agent::with('ollama');
     *
     * $openai->message('Hello from OpenAI!');
     * $ollama->message('Hello from Ollama!');
     * ```
     */
    public static function with(?string $name = null): AIAgentInterface
    {
        return self::newAgent($name);
    }

    /**
     * Return all registered agents.
     *
     * Values are either class-name strings (not yet instantiated) or live
     * `AIAgentInterface` objects.
     *
     * @return array<string,class-string<AIAgentInterface>|AIAgentInterface> Return array of registered agents.
     *
     * @example - Example:
     * ```php
     * foreach (Agent::agents() as $name => $agent) {
     *     echo $name . ': ' . (is_string($agent) ? $agent : get_class($agent));
     * }
     * ```
     */
    public static function agents(): array
    {
        return self::$agents;
    }

    /**
     * Compute the cosine similarity between two equal-length embedding vectors.
     *
     * Returns a value in the range `[-1, 1]` where `1` means identical direction,
     * `0` means orthogonal, and `-1` means opposite direction. Useful for
     * comparing embeddings produced by `AIAgentInterface::embed()`.
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
     * $score = Agent::compareCosineVector($vectors[0], $vectors[1]);
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
     * Forward instance method calls to the underlying AI agent.
     *
     * @param string $method Provider method name.
     * @param array $arguments Method arguments.
     *
     * @return mixed Return result.
     * @throws Throwable If the agent throws a non-application exception.
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
            return $this->agent->{$method}(...$arguments);
        } catch (Throwable $e) {
            if ($e instanceof LuminovaException) {
                throw $e;
            }

            throw new AIAgentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Forward static method calls to the active agent or registry.
     *
     * @param string $method Provider method name, or a registered agent key.
     * @param array $arguments Method arguments, or constructor arguments for the agent.
     *
     * @return mixed Provider instance (if `$method` is a registry key) or the method result.
     * @throws Throwable If the agent throws a non-application exception.
     *
     * @example - Calling an agent method statically:
     * ```php
     * $reply = Agent::message('Write a short story about Mars.');
     * ```
     *
     * @example - Accessing a named agent via magic static call:
     * ```php
     * $reply = Agent::Openai($apiKey)->message('Hello from OpenAI!');
     * $reply = Agent::Ollama()->message('Hello from Ollama!');
     * ```
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        if (isset(self::$agents[strtolower($method)])) {
            return self::newAgent($method, $arguments);
        }

        try{
            return self::newAgent()->{$method}(...$arguments);
        } catch (Throwable $e) {
            if ($e instanceof LuminovaException) {
                throw $e;
            }

            throw new AIAgentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Resolve or instantiate an agent by registry name.
     *
     * Reads constructor arguments from `App\Config\AI` when no explicit `$args`
     * are given. Caches the resulting instance back into `self::$agents` so
     * subsequent calls skip re-instantiation.
     *
     * @param string|null $name Registry key, or `null` to use the configured default.
     * @param array $args Explicit constructor arguments (overrides config).
     *
     * @return AIAgentInterface
     * @throws RuntimeException If the agent name is not registered.
     */
    private static function newAgent(?string $name = null, array $args = []): AIAgentInterface
    {
        static $config;

        $config ??= new AI();
        $name = strtolower($name ?? ($config->handler ?: 'openai'));

        /**
         * @var class-string<AIAgentInterface>|AIAgentInterface $class
         */
        $class = self::$agents[$name] ?? null;

        if ($class instanceof AIAgentInterface) {
            return $class;
        }

        if (!$class) {
            throw new RuntimeException(sprintf(
                'Agent with name: "%s" is not registered. 
                Add it via Agent::register() or set App\Config\AI::$handler = "%s".',
                $name,
                $name
            ));
        }

        if ($args === []) {
            $args = $class::resolveConstructorArgs($config);
        }

        self::$agents[$name] = new $class(...$args);

        if($config->model){
            self::$agents[$name]->setModel($config->model);
        }

        return self::$agents[$name];
    }
}