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
namespace Luminova\AI\Client;

use \Luminova\Base\AI;
use \App\Config\AI as AIConfig;
use function \Luminova\Funcs\{root, make_dir};
use \Luminova\Exceptions\{AIException, RuntimeException};

/**
 * Ollama client for the Luminova AI manager.
 *
 * @link https://luminova.ng/docs/0.0.80/ai-client/ollama
 */
class Ollama extends AI
{
    /**
     * {@inheritDoc}
     */
    protected array $endpoints = [
        'generate'   => 'generate',
        'responses'  => 'chat',
        'embeddings' => 'embed',
        'models'     => 'tags',
        'show'       => 'show',
        'pull'       => 'pull',
        'push'       => 'push',
        'delete'     => 'delete',
        'create'     => 'create',
        'search'     => 'web_search',
    ];

    /**
     * Create a new Ollama client instance.
     *
     * @param string|null $baseUrl Base URL of the Ollama server (default: `http://localhost:11434/api/`).
     * @param string|null $apiKey Optional Bearer token for authenticated Ollama deployments.
     * 
     * @see \App\Config\AI For default application AI configuration.
     *
     * @example - Local default:
     * ```php
     * $client = new Ollama();
     * $reply = $client->message('Explain recursion in plain English.');
     * ```
     *
     * @example - Remote / authenticated deployment:
     * ```php
     * $client = new Ollama('https://ollama.example.com/api/', env('OLLAMA_KEY'));
     * ```
     */
    public function __construct(?string $baseUrl = null, ?string $apiKey = null)
    {
        $baseUrl ??= 'http://localhost:11434/api/';
        parent::__construct(
            $baseUrl, 
            $apiKey, 
            initHttpClient: true
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function resolveConstructorArgs(AIConfig $config): array
    {
        return [
            $config->baseUrl,
            $config->apiKey,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function generate(string $prompt, array $options = []): array
    {
        $url = $this->endpoint('generate');
        $options['prompt'] = $prompt;
        $options['stream'] ??= false;
        $this->parseOptions($options);

        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        return $this->send('POST', $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function chat(array|string $messages, array $options = []): array
    {
        $url = $this->endpoint('responses');

        if (is_string($messages)) {
            $messages = [['role' => 'user', 'content' => $messages]];
        } elseif (isset($messages['role'])) {
            $messages = [$messages];
        }

        $options['messages'] = $messages;
        $options['stream'] ??= false;
        $this->parseOptions($options);
        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        $res = $this->send('POST', $url, $options);

        return $res['message'] ?? $res;
    }

    /**
     * {@inheritdoc}
     */
    public function webSearch(string $query, array $options = []): array
    {
        $url = $this->endpoint('search');
        $options['query'] = $query;
        $this->parseOptions($options);

        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        return $this->send('POST', $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function embed(string|array $input, array $options = []): array
    {
        $url = $this->endpoint('embeddings');

        $options['model'] ??= $this->model ?? 'nomic-embed-text';
        $options['input']   = $input;

        $this->parseOptions($options);
        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        $res = $this->send('POST', $url, $options);

        $vectors = $res['embeddings'] ?? [];

        if ($vectors === []) {
            return [];
        }

        return is_string($input) ? ($vectors[0] ?? []) : $vectors;
    }

    /**
     * {@inheritdoc}
     */
    public function vision(string $prompt, string|array $images, array $options = []): array
    {
        $messages = [];
 
        foreach ((array) $images as $image) {
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                $raw = @file_get_contents($image);
 
                if ($raw === false) {
                    throw new RuntimeException("Failed to fetch vision image from URL: {$image}");
                }
 
                $messages[] = base64_encode($raw);
                continue;
            }
            
            if (file_exists($image)) {
                $raw = file_get_contents($image);
 
                if ($raw === false) {
                    throw new RuntimeException("Vision image file is not readable: {$image}");
                }
 
                $messages[] = base64_encode($raw);
                continue;
            } 
            if (str_contains($image, ';base64,')) {
                $image = substr($image, strpos($image, ';base64,') + 8);
            }

            $messages[] = $image;
        }
 
        $message = [
            'role'    => 'user',
            'content' => $prompt,
            'images'  => $messages,
        ];
 
        $options['model'] ??= $this->model ?? 'llava';
 
        return $this->chat($message, $options);
    }

    /**
     * Creates a custom model.
     *
     * - An absolute path to a `.modelfile` text file
     * - An absolute path to a JSON file (auto-converted via `toModelFile()`)
     * - A raw Modelfile string
     * 
     * @param string $model  Suffix or display name for the resulting fine-tuned model.
     * @param array|string $modelfile Training model file path, array of modelfile configuration.
     * @param array<string,mixed> $options Optional training parameters.
     *     @type string from Base model to fine-tune (e.g. `llama3`).
     *     @type string system  System prompt to embed in the modelfile (Ollama only).
     *
     * @return array Return model creation result.
     * @throws AIException On network, client errors or malformed JSON in the response.
     *
     * @example - Example:
     * ```php
     * $result = $provider->create('my-assistant', '/path/to/dataset.modelfile', [
     *     'system' => 'You are a helpful PHP assistant.',
     * ]);
     * ```
     * 
     * @example - Create Model:
     * ```php
     * $model = $client->create('php-assistant', 
     *      modelfile: [
     *          'parameters' => [
     *              'temperature' => 0.2,
     *              'top_p'       => 0.9,
     *          ]
     *      ],
     *      options: [
     *          'from' => 'llama3',
     *          'system' => 'You are a helpful assistant specialized in PHP.',
     *      ]
     * );
     * ```
     */
    public function create(string $model, array|string $modelfile, array $options = []): array
    {
        $url = $this->endpoint('create');

        $isFile = is_file($modelfile);
        $isAutoDelete = isset($options['auto_delete_file']);

        if ($isFile){
            $content = @file_get_contents($modelfile);

            if ($content && json_validate($content)) {
                $content = self::toModelFile(json_decode($content, true));
            }
        } elseif (is_array($modelfile)){
           $content = self::toModelFile($modelfile);
        } else {
            $content = $modelfile;
        }

        if (!$content) {
            throw new RuntimeException("Modelfile is empty or not readable: {$modelfile}");
        }

        $from = $options['from'] ?? $options['base'] ?? $options['model'] ?? $this->model ?? 'llama3';
        $system = isset($options['system']) && $options['system'] !== ''
            ? 'SYSTEM ' . $options['system'] . "\n"
            : '';

        unset($options['auto_delete_file'], $options['from'], $options['base']);

        $options['stream'] ??= false;
        $options['name']   = $model;
        $options['modelfile'] = "FROM {$from}\n\n{$system}\n{$content}";

        $this->parseOptions($options);

        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        $res = $this->send('POST', $url, $options);

        if ($isFile && $isAutoDelete) {
            @unlink($modelfile);
        }

        return $res;
    }

    /**
     * {@inheritdoc}
     * @see self::create() Wrapper for create()
     */
    public function fineTune(string $model, string $dataset, array $options = []): array 
    {
       return $this->create($model, $dataset, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function fineTuneDataset(array $dataset, array $options = []): array
    {
        $path = root('writeable/ai/training/');

        if (!make_dir($path)) {
            throw new RuntimeException("Failed to create training directory: {$path}");
        }

        $file = $path . uniqid() . '.modelfile';
        $fh   = fopen($file, 'w');

        if ($fh === false) {
            throw new RuntimeException("Failed to write dataset to: {$file}");
        }

        if (array_is_list($dataset)) {
            foreach ($dataset as $entry) {
                fwrite($fh, self::toModelFile($entry) . "\n");
            }
        } else {
            fwrite($fh, self::toModelFile($dataset) . "\n");
        }

        fclose($fh);

        $options['auto_delete_file'] = true;

        return $this->create($options['suffix'] ?? $options['name'] ?? 'fine-tuned', $file, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function fineTuneStatus(string $jobId): array
    {
        $model = $this->models($jobId);

        return [
            'status' => ($model === []) ? 'running' : 'succeeded',
            'fine_tuned_model'  => $model,
        ];
    }

    /**
     * Delete a locally-stored model.
     *
     * @param string $model Model name to delete (e.g. `llama3`).
     *
     * @return array Ollama API response.
     *
     * @example - Example:
     * ```php
     * $client->delete('llama3');
     * ```
     */
    public function delete(string $model): array
    {
        $url = $this->endpoint('delete');

        $this->parseOptions(['name' => $model]);

        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        return $this->send('DELETE', $url, $options);
    }

    /**
     * Pull (download) a model from the Ollama model registry.
     *
     * @param string $model Model name to pull (e.g. `mistral`, `llama3:8b`).
     *
     * @return array Ollama API response containing pull progress details.
     *
     * @example - Example:
     * ```php
     * $client->pull('mistral');
     * ```
     */
    public function pull(string $model): array
    {
        $url = $this->endpoint('pull');
        $this->parseOptions(['name' => $model]);

        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        return $this->send('POST', $url, $options);
    }

    /**
     * Push a locally-created model to the Ollama model registry.
     *
     * @param string $model Model name to push (must exist locally).
     *
     * @return array Ollama API response.
     *
     * @example - Example:
     * ```php
     * $client->push('my-org/my-assistant');
     * ```
     */
    public function push(string $model): array
    {
        $url = $this->endpoint('push');
        $this->parseOptions(['name' => $model]);

        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        return $this->send('POST', $url, $options);
    }
}