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
namespace Luminova\Interface;

use \BackedEnum;
use \Luminova\AI\Model;
use \App\Config\AI as AIConfig;
use \Luminova\Exceptions\AIException;

/**
 * Supported AI clients.
 *
 * @see Luminova\AI\Client\Ollama
 * @see Luminova\AI\Client\OpenAi
 * @see Luminova\AI\Client\Anthropic
 */
interface AIClientInterface
{
    /**
     * Resolve constructor arguments for the AI client instance.
     *
     * This method is responsible for mapping configuration values to the
     * constructor parameters required by the specific client implementation.
     * It is intended to be overridden by subclasses so each client can define
     * its own initialization logic.
     *
     * The base implementation returns an empty array, meaning no arguments
     * will be passed to the constructor unless overridden.
     *
     * @param \App\Config\AI $config Shared configuration instance.
     *
     * @return array<int,mixed> List of arguments to pass into the client constructor.
     *
     * @example - Override:
     * ```php
     * protected static function resolveConstructorArgs(AIConfig $config): array
     * {
     *     return [
     *         $config->apiKey,
     *         $config->organization,
     *         $config->project,
     *     ];
     * }
     * ```
     *
     * @example - No Constructor Arguments:
     * ```php
     * protected static function resolveConstructorArgs(AIConfig $config): array
     * {
     *     return [];
     * }
     * ```
     */
    public static function resolveConstructorArgs(AIConfig $config): array;

    /**
     * Set the client's API base URL.
     *
     * Useful when targeting a self-hosted or proxied endpoint instead
     * of the client's default public API URL.
     *
     * @param string $baseurl Fully-qualified base URL (e.g. `http://localhost:11434/api/`).
     *
     * @return static Returns instance of client class.
     *
     * @example - Example:
     * ```php
     * $client->setBaseUrl('https://my-proxy.example.com/v1/');
     * ```
     */
    public function setBaseUrl(string $baseurl): self;

    /**
     * Set the default model for all subsequent AI requests.
     *
     * When set, this model will be used unless overridden via `$options['model']`
     * in individual method calls.
     *
     * @param BackedEnum|Model<BackedEnum>|string $model Model identifier or enum model 
     *          (e.g, `Model::GPT_4_1_MINI` or `gpt-4.1-mini`).
     *
     * @return static Returns instance of client class.
     *
     * @example - Example:
     * ```php
     * $client->setModel('gpt-4.1');
     * $response = $client->message('Hello!');
     * ```
     */
    public function setModel(object|string $model): self;

    /**
     * Set the default user ID for all subsequent AI requests.
     *
     * When set, this user will be used unless overridden via `$options['user']`
     * in individual method calls.
     *
     * @param string|int $user The request users identifier.
     *
     * @return static Returns instance of client class.
     *
     * @example - Example:
     * ```php
     * $response = $client->user('user100')
     *      ->message('Hello!');
     * ```
     */
    public function user(string|int $user): self;

    /**
     * Generate a text completion for a given prompt.
     *
     * Sends a single prompt string and returns the generated response.
     * Internally delegates to `message()` or the client's native
     * completion endpoint.
     *
     * @param string $prompt  The input prompt to complete.
     * @param array  $options Optional request parameters.
     *     @type string model       Model identifier. Default varies by client.
     *     @type float  temperature Sampling temperature (0–2). Default `0.7`.
     *     @type int    max_tokens  Maximum tokens to generate. Default `1024`.
     *     @type float  top_p       Nucleus sampling probability (0–1). Default `1.0`.
     *
     * @return array Generated completion output.
     * @throws AIException On network, client errors or malformed JSON in the response.
     *
     * @example - Example:
     * ```php
     * $result = $client->generate('Explain recursion in simple terms.');
     * ```
     */
    public function generate(string $prompt, array $options = []): array;

    /**
     * Send a chat conversation and receive a reply.
     *
     * Accepts either a pre-built messages array (multi-turn) or a raw
     * string (treated as a single user message). All clients normalize
     * the input into the format their API expects.
     *
     * @param array|string $messages Single user message string, a single message
     *                               associative array (`['role' => 'user', 'content' => '...']`),
     *                               or a full conversation array of message objects.
     * @param array<string,mixed> $options  Optional request parameters.
     *     @type string model       Model identifier.
     *     @type float  temperature Sampling temperature.
     *     @type int    max_tokens  Maximum tokens in the reply.
     *     @type string user        End-user identifier for abuse monitoring.
     *
     * @return array The assistant's reply message(s).
     * @throws AIException On network, client errors or malformed JSON in the response.
     *
     * @example - Single message:
     * ```php
     * $reply = $client->chat('What is the speed of light?');
     * ```
     *
     * @example - Multi-turn conversation:
     * ```php
     * $reply = $client->chat([
     *     ['role' => 'system',    'content' => 'You are a helpful assistant.'],
     *     ['role' => 'user',      'content' => 'Who won the 1966 World Cup?'],
     *     ['role' => 'assistant', 'content' => 'England won the 1966 FIFA World Cup.'],
     *     ['role' => 'user',      'content' => 'Where was it held?'],
     * ]);
     * ```
     * 
     * @example - With explicit options:
     * ```php
     * $content = $client->chat('Write a limerick about PHP.', [
     *     'model'       => 'claude-sonnet-4-6',
     *     'max_tokens'  => 256,
     *     'temperature' => 0.9,
     * ]);
     * ```
     */
    public function chat(array|string $messages, array $options = []): array;

    /**
     * Send a single user message and receive a reply.
     *
     * Convenience wrapper around `chat()` for simple single-turn interactions.
     *
     * @param string $prompt The user message to send.
     * @param array  $options Optional request parameters (see `chat()`).
     *
     * @return array The assistant's reply message(s).
     * @throws AIException On network, client errors or malformed JSON in the response.
     *
     * @example - Example:
     * ```php
     * $reply = $client->message('Summarize the French Revolution in 3 sentences.');
     * ```
     */
    public function message(string $prompt, array $options = []): array;

    /**
     * Generate images or edit image from a text prompt.
     *
     * Returns an array of image URLs or base64-encoded image data depending
     * on the `response_format` option, or `false` if the client does not
     * support image generation (e.g. Ollama).
     * 
     * Sends a source image (and an optional mask) together with a prompt so
     * the model can modify only the designated area.
     *
     * @param string $prompt  Descriptive text prompt for the image.
     * @param array  $options Optional request parameters.
     *     @type string model           Image model (e.g. `gpt-image-1`, `dall-e-3`).
     *     @type string size            Image dimensions (e.g. `1024x1024`). Default `1024x1024`.
     *     @type string response_format Output format: `url` or `b64_json`. Default `url`.
     *     @type int    n               Number of images to generate. Default `1`.
     *     @type array edits
     *          @type string image Absolute path to the source image (PNG, max 4 MB).
     *          @type string mask  Absolute path to the mask image (PNG, max 4 MB, transparent areas are edited).
     *
     * @return array|false Array of image result objects, or `false` if unsupported.
     * @throws AIException On network, client errors or malformed JSON in the response.
     *
     * @example - Example:
     * ```php
     * $images = $client->image('A serene mountain lake at sunset, oil painting style.');
     * $url = $images[0]['url'] ?? null;
     * ```
     *
     * @example
     * ```php
     * $images = $client->image('A futuristic city skyline at night, digital art.', [
     *     'size'            => '1024x1024',
     *     'response_format' => 'url',
     *     'n'               => 2,
     * ]);
     *
     * foreach ($images as $img) {
     *     echo $img['url'] . PHP_EOL;
     * }
     * ```
     * 
     * @example - OpenAI Edit Image
     * ```php
     * $results = $client->image('Replace the sky with a dramatic sunset.', [
     *      'size'  => '1024x1024',
     *      'edits' => [
     *          'image' => '/path/to/original.png',
     *          'mask'  => '/path/to/mask.png',
     *      ]
     * ]);
     * echo $results[0]['url'] ?? '';
     * ```
     */
    public function image(string $prompt, array $options = []): array|bool;

    /**
     * Send one or more images with a text prompt for visual analysis.
     *
     * It builds a multimodal user message containing image content blocks followed
     * by the text prompt.
     *
     * Each entry in `$images` accepts one of:
     * - A URL string (`https://…`) — sent as `{"type":"url","url":"…"}`
     * - An absolute local file path — base64-encoded and sent as
     *   `{"type":"base64","media_type":"…","data":"…"}`
     * - A pre-built content block array (passed through unchanged)
     *
     * @param string $prompt  Text instruction for the model.
     * @param string|string[] $images  Image URL(s), file path(s), or pre-built blocks.
     * @param array $options Optional request parameters (see `chat()`).
     *
     *     @type string model Model identifier. Default `claude-opus-4-6`.
     *     @type int max_tokens Max tokens. Default `1024`.
     *
     * @return array The assistant's `content` array.
     * @throws AIException On network, client errors or malformed JSON in the response.
     *
     * @example - Analyze an image by URL:
     * ```php
     * $content = $client->vision(
     *     'Describe what you see in this image.',
     *     'https://upload.wikimedia.org/wikipedia/commons/a/a7/Camponotus_flavomarginatus_ant.jpg'
     * );
     * echo $content[0]['text'] ?? '';
     * ```
     *
     * @example - Analyze a local file with a specific question:
     * ```php
     * $content = $client->vision(
     *     'Is there any text in this screenshot? If so, transcribe it.',
     *     '/tmp/screenshot.png',
     *     ['model' => 'claude-sonnet-4-6']
     * );
     * echo $content[0]['text'] ?? '';
     * ```
     *
     * @example - Compare two images:
     * ```php
     * $content = $client->vision(
     *     'Which of these two logos is more recognizable and why?',
     *     ['https://example.com/logo-a.png', 'https://example.com/logo-b.png']
     * );
     * ```
     */
    public function vision(string $prompt, string|array $images, array $options = []): array;

    /**
     * Generate a vector embedding for the given text input.
     *
     * Embeddings are dense numeric vector representations of text, useful
     * for semantic search, clustering, RAG (retrieval-augmented generation),
     * and recommendation systems. Pass a string for a single embedding or an
     * array of strings for batch embeddings.
     *
     * @param string|array $input A single text string or an array of text strings.
     * @param array $options Optional request parameters.
     *     @type string model           Embedding model (e.g. `text-embedding-3-small`, `nomic-embed-text`).
     *     @type string encoding_format Vector format: `float` or `base64`. Default `float`.
     *     @type int    dimensions      Truncate output vector to this size (OpenAI only, optional).
     *     @type string user            End-user identifier (optional).
     *
     * @return array Flat float array for single input, or array of float arrays for batch input.
     * @throws AIException On network, client errors or malformed JSON in the response.
     *
     * @example - Single embedding:
     * ```php
     * $vector = $client->embed('The quick brown fox');
     * // $vector === [0.012, -0.034, ...]
     * ```
     *
     * @example - Batch embeddings with cosine similarity:
     * ```php
     * $vectors = $client->embed(['Hello world', 'Hi there']);
     * // $vectors === [[0.012, ...], [0.018, ...]]
     * $similarity = AI::compareVector($vectors[0], $vectors[1]);
     * ```
     */
    public function embed(string|array $input, array $options = []): array;

    /**
     * Convert text to speech and save to a local file.
     *
     * Returns the public URL of the saved audio file on success.
     * Returns `false` if the client does not support TTS (e.g. Ollama).
     *
     * @param string $text    The text to convert to audio.
     * @param array  $options Optional request parameters.
     *
     *     @type string model  TTS model (e.g. `gpt-4o-mini-tts`). Default `gpt-4o-mini-tts`.
     *     @type string voice  Voice identifier: `alloy`, `echo`, `fable`, `onyx`, `nova`, `shimmer`. Default `alloy`.
     *     @type float  speed  Playback speed (0.25–4.0). Default `1.0`.
     *     @type string response_format Audio format: `mp3`, `opus`, `aac`, `flac`, `wav`, `pcm`. Default `mp3`.
     *     @type string path    Directory path to save the audio file. Default `writeable/ai/speech`.
     *     @type string symlink Optional public directory path for a symbolic link to the saved file.
     *
     * @return string|false Absolute URL to the saved file, or `false` on failure/unsupported.
     * @throws AIException On network, client errors or malformed JSON in the response.
     *
     * @example - Example:
     * ```php
     * $url = $client->speech('Welcome to Luminova!', ['voice' => 'nova']);
     * // $url === 'https://example.com/writeable/ai/speech/6789abc.mp3'
     * ```
     */
    public function speech(string $text, array $options = []): string|bool;

    /**
     * Transcribe or translate audio to text.
     *
     * Sends an audio file to the client's transcription endpoint and
     * returns the recognized text. Returns `false` if the client does
     * not support audio transcription (e.g. Ollama).
     *
     * @param string $prompt   Optional context hint to guide transcription accuracy.
     * @param string $filename Absolute path to the local audio file.
     * @param array  $options  Optional request parameters.
     *
     *     @type string model           Transcription model (e.g. `whisper-1`). Default `whisper-1`.
     *     @type string language        BCP-47 language code for the audio (e.g. `en`). Default auto-detect.
     *     @type string response_format Output format: `json`, `text`, `srt`, `vtt`. Default `json`.
     *     @type float  temperature     Sampling temperature. Default `0`.
     *
     * @return string|false Transcribed text on success, `false` on failure/unsupported.
     * @throws AIException On network, client errors or malformed JSON in the response.
     *
     * @example - Example:
     * ```php
     * $text = $client->audio('Transcribe this meeting recording.', '/tmp/meeting.mp3');
     * ```
     */
    public function audio(string $prompt, string $filename, array $options = []): string|bool;

    /**
     * Retrieve a list of available models.
     *
     * @return array Array of model objects (list).
     * @throws AIException On network, client errors or malformed JSON in the response.
     *
     * @example - List all models
     * ```php
     * $models = $client->models();
     * foreach ($models as $model) {
     *     echo $model['id'] . PHP_EOL;
     * }
     * ```
     */
    public function models(): array;

    /**
     * Retrieve detailed information about a specific model.
     *
     * @param string $name Model identifier.
     *
     * @return array Model detail array.
     * @throws AIException On network, client errors or malformed JSON in the response.
     *
     * @example - Example:
     * ```php
     * $info = $client->model('llama3');
     * echo $info['modified_at']; // Ollama
     * ```
     */
    public function model(string $name): array;

    /**
     * Perform a web search and return AI-augmented results.
     *
     * The client queries the web and returns a structured response that
     * may include cited sources, snippets, or a synthesized answer.
     *
     * @param string $query The search query string.
     * @param array $options Optional client-specific parameters.
     *     @type string model  Model identifier (OpenAI: uses built-in web_search_preview tool).
     *     @type int    limit  Maximum number of results to return (Ollama-specific).
     *
     * @return array Search results or AI-synthesized answer array.
     *
     * @throws RuntimeException On network or client errors.
     * @throws JsonException    On malformed JSON in the response.
     *
     * @example - Example:
     * ```php
     * $results = $client->webSearch('Latest PHP 8.4 features');
     * ```
     * 
     * @example - Restricted to trusted domains with location context
     * ```php
     * $results = $client->webSearch('Current ringgit exchange rate', [
     *     'allowed_domains' => ['bnm.gov.my', 'xe.com'],
     *     'user_location'   => [
     *         'type'     => 'approximate',
     *         'city'     => 'Kuala Lumpur',
     *         'country'  => 'MY',
     *         'timezone' => 'Asia/Kuala_Lumpur',
     *     ],
     *     'max_uses' => 3,
     * ]);
     * ```
     */
    public function webSearch(string $query, array $options = []): array;

    /**
     * Fine-tune a model with a custom dataset.
     *
     * Submits a training job to the client using either an uploaded
     * file ID (OpenAI) or a raw modelfile/dataset path (Ollama). The
     * returned array contains the job details, which can be polled via
     * `fineTuneStatus()`.
     *
     * @param string $model   Suffix or display name for the resulting fine-tuned model.
     * @param string $dataset Training file path, raw modelfile content, or uploaded file ID.
     * @param array  $options Optional training parameters.
     *     @type string model            Base model to fine-tune (e.g. `gpt-4.1-mini`, `llama3`).
     *     @type int    n_epochs         Training epochs. Default `auto`.
     *     @type string suffix           Custom suffix appended to the fine-tuned model name.
     *     @type bool   auto_delete_file Automatically delete the training file after upload (default `false`).
     *     @type string system           System prompt to embed in the modelfile (Ollama only).
     *
     * @return array Fine-tuning job response or model creation result.
     * @throws AIException On network, client errors or malformed JSON in the response.
     *
     * @example - OpenAI:
     * ```php
     * $job = $client->fineTune('my-model', '/path/to/training.jsonl', [
     *     'model' => 'gpt-4.1-mini',
     * ]);
     * $jobId = $job['id'];
     * ```
     *
     * @example - Ollama:
     * accepts:
     *
     * - An absolute path to a `.modelfile` text file
     * - An absolute path to a JSON file (auto-converted via `toModelFile()`)
     * - A raw Modelfile string
     *
     * @example
     * ```php
     * $result = $client->fineTune('my-llama', '/path/to/dataset.modelfile', [
     *     'system' => 'You are a helpful PHP assistant.',
     * ]);
     * ```
     */
    public function fineTune(string $model, string $dataset, array $options = []): array;

    /**
     * Prepare and submit a structured dataset array for fine-tuning.
     *
     * Converts a high-level `[input => ..., output => ...]` array into the
     * client's native training format (JSONL for OpenAI, Modelfile for Ollama),
     * writes it to a temporary file, and calls `fineTune()` automatically.
     *
     * @param array<int, array{input: string, output: string}> $dataset Training examples.
     * @param array $options Optional training parameters (see `fineTune()`).
     *
     *     @type string suffix  Suffix for the fine-tuned model name. Default `fine-tuned`.
     *     @type string model   Base model identifier.
     *     @type string system  System prompt (Ollama only).
     *
     * @return array Fine-tuning job response (see `fineTune()`).
     *
     * @throws AIException On network, client errors or malformed JSON in the response.
     * @throws RuntimeException If the training file cannot be written.
     *
     * @example - Example:
     * ```php
     * $job = $client->fineTuneDataset([
     *     ['input' => 'What is your name?',  'output' => 'I am Lumi.'],
     *     ['input' => 'Who made you?',        'output' => 'Nanoblock Technology Ltd.'],
     * ], ['suffix' => 'lumi-v1', 'model' => 'gpt-4.1-mini']);
     * ```
     *
     * @example - Converts a structured dataset:
     * 
     * Converts a structured dataset array into Modelfile format, writes it to
     * a temporary file under `writeable/ai/training/`, and calls `fineTune()`.
     * The temporary file is deleted automatically after processing.
     * 
     * ```php
     * $result = $client->fineTuneDataset([
     *     'parameters' => ['temperature' => 0.7, 'max_tokens' => 256],
     *     'messages'   => [
     *         ['prompt' => 'What is PHP?',   'completion' => 'A server-side scripting language.'],
     *         ['prompt' => 'What is MySQL?', 'completion' => 'An open-source relational database.'],
     *     ],
     * ], [
     *     'suffix' => 'php-assistant',
     *     'system' => 'You are a PHP expert.',
     * ]);
     * ```
     */
    public function fineTuneDataset(array $dataset, array $options = []): array;

    /**
     * Poll the status of a fine-tuning job.
     *
     * @param string $jobId The fine-tuning job ID returned by `fineTune()` or
     *                      the model name to check (Ollama).
     *
     * @return array Status response. Contains at minimum a `status` key
     *               (`queued`, `running`, `succeeded`, `failed` for OpenAI;
     *               `building` or `ready` for Ollama).
     * @throws RuntimeException On network or client errors.
     *
     * @example - Example:
     * ```php
     * $status = $client->fineTuneStatus($job['id']);
     * if ($status['status'] === 'succeeded') {
     *     $modelName = $status['fine_tuned_model'];
     * }
     * ```
     */
    public function fineTuneStatus(string $jobId): array;
}