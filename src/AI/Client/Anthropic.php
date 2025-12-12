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
use \Luminova\Exceptions\{AIException, RuntimeException};

/**
 * Anthropic AI client for the Luminova AI manager.
 *
 * @link https://luminova.ng/docs/0.0.80/ai-client/anthropic
 */
class Anthropic extends AI
{
    /**
     * {@inheritDoc}
     */
    protected array $endpoints = [
        'messages' => 'messages',
        'models'   => 'models',
        'batches'  => 'messages/batches',
    ];

    /**
     * Active `anthropic-beta` feature flags (comma-separated).
     *
     * When non-null, this string is sent as the `anthropic-beta` request
     * header to opt into Anthropic beta features.
     *
     * @var string|null $betaFeatures
     */
    private ?string $betaFeatures = null;

    /**
     * Latest web search tool version (supports dynamic filtering with Claude 4).
     *
     * Pass `['search_tool' => 'web_search_20250305']` in `$options` to use the
     * older ZDR-eligible version without dynamic filtering.
     *
     * @var string WEB_SEARCH_TOOL
     */
    private const WEB_SEARCH_TOOL = 'web_search_20260209';

    /**
     * Create a new Anthropic AI client instance.
     *
     * @param string|null $baseUrl API base URL (default: hhttps://api.anthropic.com/v1/).
     * @param string $apiKey Anthropic secret API key (`sk-ant-…`).
     * @param string $version API version header value (default: `2023-06-01` current stable).
     * @param string|null $betaFeatures Optional comma-separated beta feature flags sent via `anthropic-beta` header.
     *                     (e.g, `'interleaved-thinking-2025-05-14'`).
     * 
     * @see \App\Config\AI For default application AI configuration.
     *
     * @example - Basic usage:
     * ```php
     * $client = new Anthropic(apiKey: env('ANTHROPIC_API_KEY'));
     * $reply = $client->message('Hello, Anthropic!');
     * ```
     *
     * @example - With beta features enabled:
     * ```php
     * $client = new Anthropic(
     *     apiKey:       env('ANTHROPIC_API_KEY'),
     *     betaFeatures: 'interleaved-thinking-2025-05-14',
     * );
     * $reply = $client->message('Reason carefully: what is 17 × 43?');
     * ```
     */
    public function __construct(
        ?string $baseUrl = null,
        ?string $apiKey = null,
        ?string $version = null,
        ?string $betaFeatures = null
    ) 
    {
        $this->options['max_tokens'] = 1024;
        $this->betaFeatures = $betaFeatures;

        $headers = [
            'x-api-key'         => $apiKey,
            'anthropic-version' => $version ?? '2023-06-01',
            'Content-Type'      => 'application/json',
        ];

        if ($betaFeatures !== null) {
            $headers['anthropic-beta'] = $betaFeatures;
        }

        $baseUrl ??= 'https://api.anthropic.com/v1/';

        parent::__construct(
            $baseUrl, 
            $apiKey, 
            $headers,
            true
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
            $config->version,
            $config->betaFeatures,
        ];
    }

    /**
     * Enable one or more Anthropic beta features for all subsequent requests.
     *
     * Sets the `anthropic-beta` request header. Multiple flags can be passed
     * as a comma-separated string. Passing `null` clears any active flags.
     *
     * @param string|null $features Comma-separated beta feature flag(s), or
     *                              `null` to disable beta headers.
     *
     * @return static Return instance of class.
     *
     * @link https://docs.anthropic.com/en/api/beta-headers
     *
     * @example - Example:
     * ```php
     * $client->setBetaFeatures('interleaved-thinking-2025-05-14');
     * $reply = $client->message('Solve this step by step: …');
     * ```
     *
     * @example - Clear beta features:
     * ```php
     * $client->setBetaFeatures(null);
     * ```
     */
    public function setBetaFeatures(?string $features = null): static
    {
        $this->betaFeatures = $features;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function chat(array|string $messages, array $options = []): array
    {
        $url = $this->endpoint('messages');

        if (is_string($messages)) {
            $messages = [['role' => 'user', 'content' => $messages]];
        } elseif (isset($messages['role'])) {
            $messages = [$messages];
        }

        if (!isset($options['system'])) {
            foreach ($messages as $idx => $msg) {
                if (($msg['role'] ?? '') === 'system') {
                    $options['system'] = is_array($msg['content'])
                        ? $msg['content']
                        : (string) $msg['content'];
                    unset($messages[$idx]);
                    $messages = array_values($messages);
                    break;
                }
            }
        }

        if (!empty($options['user'])) {
            $options['metadata']['user_id'] = $options['user'];
        }
        unset($options['user']);

        $options['messages'] = $messages;
        $this->parseOptions($options);
        $this->buildHeaders();

        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        $res = $this->send('POST', $url, $options);

        return $res['content'] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function webSearch(string $query, array $options = []): array
    {
        $tool = [
            'type' => $options['search_tool'] ?? self::WEB_SEARCH_TOOL, 
            'name' => 'web_search'
        ];

        if (isset($options['max_uses'])) {
            $tool['max_uses'] = (int) $options['max_uses'];
        }

        if (isset($options['allowed_domains'])) {
            $tool['allowed_domains'] = (array) $options['allowed_domains'];
        } elseif (isset($options['blocked_domains'])) {
            $tool['blocked_domains'] = (array) $options['blocked_domains'];
        }

        if (isset($options['user_location'])) {
            $tool['user_location'] = $options['user_location'];
        }

        $options['tools'][] = $tool;

        unset(
            $options['search_tool'],
            $options['max_uses'],
            $options['allowed_domains'],
            $options['blocked_domains'],
            $options['user_location']
        );

        return $this->chat($query, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function vision(string $prompt, string|array $images, array $options = []): array
    {
        $messages = [];

        foreach ((array) $images as $image) {
            if (is_array($image)) {
                $messages[] = $image;
                continue;
            }

            if (filter_var($image, FILTER_VALIDATE_URL)) {
                $messages[] = [
                    'type'   => 'image',
                    'source' => ['type' => 'url', 'url' => $image],
                ];
            } else {
                if (!file_exists($image)) {
                    throw new AIException("Vision image file does not exist: {$image}");
                }

                $raw  = file_get_contents($image);
 
                if ($raw === false) {
                    throw new RuntimeException("Vision image file is not readable: {$image}");
                }

                $data = base64_encode($raw);
                $extension = strtolower(pathinfo($image, PATHINFO_EXTENSION));

                $mediaType = match ($extension) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png'         => 'image/png',
                    'gif'         => 'image/gif',
                    'webp'        => 'image/webp',
                    default       => 'image/jpeg',
                };

                $messages[] = [
                    'type'   => 'image',
                    'source' => [
                        'type'       => 'base64',
                        'media_type' => $mediaType,
                        'data'       => $data,
                    ],
                ];
            }
        }

        $messages[] = ['type' => 'text', 'text' => $prompt];

        return $this->chat(
            ['role' => 'user', 'content' => $messages],
            $options
        );
    }
    
    /**
     * Submit a batch of message requests for asynchronous processing.
     *
     * The Message Batches API processes large volumes of requests at 50% of
     * the standard per-token cost. Each item in `$requests` must be a
     * `['custom_id' => string, 'params' => array]` entry where `params`
     * follows the standard Messages API body shape.
     *
     * @param array<int, array{custom_id: string, params: array}> $requests Batch items.
     * @param array $options Optional additional request parameters.
     *
     * @return array Batch creation response containing `id`, `processing_status`,
     *               `request_counts`, `created_at`, `expires_at`, and more.
     *
     * @throws RuntimeException On network or client errors.
     * @throws JsonException    On malformed JSON in the response.
     *
     * @link https://docs.anthropic.com/en/api/creating-message-batches
     *
     * @example - Example:
     * ```php
     * $batch = $client->batch([
     *     [
     *         'custom_id' => 'req-001',
     *         'params'    => [
     *             'model'     => 'claude-haiku-4-5-20251001',
     *             'max_tokens' => 128,
     *             'messages'  => [['role' => 'user', 'content' => 'Translate "hello" to French.']],
     *         ],
     *     ],
     *     [
     *         'custom_id' => 'req-002',
     *         'params'    => [
     *             'model'     => 'claude-haiku-4-5-20251001',
     *             'max_tokens' => 128,
     *             'messages'  => [['role' => 'user', 'content' => 'Translate "hello" to Spanish.']],
     *         ],
     *     ],
     * ]);
     *
     * $batchId = $batch['id'];
     * // Poll batchStatus($batchId) until processing_status === 'ended'.
     * ```
     */
    public function batch(array $requests, array $options = []): array
    {
        $url = $this->endpoint('batches');

        $options = array_merge($options, [
            'requests' => array_map(static function (array $item): array {
                return [
                    'custom_id' => $item['custom_id'],
                    'params'    => $item['params'],
                ];
            }, $requests),
        ]);

        $this->parseOptions($options);
        $this->buildHeaders();

        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        return $this->send('POST', $url, $options);
    }

    /**
     * Poll the processing status of a message batch.
     *
     * @param string $batchId Batch ID returned by `batch()`.
     *
     * @return array Batch status object. Key `processing_status` is either
     *               `'in_progress'` or `'ended'`. When `'ended'`, retrieve
     *               results via the `results_url` field or the Anthropic console.
     *
     * @throws RuntimeException On network or client errors.
     *
     * @example - Example:
     * ```php
     * do {
     *     $status = $client->batchStatus($batchId);
     *     sleep(5);
     * } while ($status['processing_status'] === 'in_progress');
     *
     * echo 'Batch ended. Results: ' . $status['results_url'];
     * ```
     */
    public function batchStatus(string $batchId): array
    {
        $url = $this->endpoint('batches', '/' . $batchId);
        $this->buildHeaders();

        $options = [];

        if($this->options){
            $options['query'] = $this->options;
        }

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        return $this->send('GET', $url, $options);
    }

    /**
     * Cancel an in-progress message batch.
     *
     * @param string $batchId Batch ID to cancel.
     *
     * @return array Updated batch object (processing_status will become `'ended'`
     *               once cancellation is complete).
     *
     * @throws RuntimeException On network or client errors.
     *
     * @example - Example:
     * ```php
     * $result = $client->cancelBatch('msgbatch_abc123');
     * ```
     */
    public function cancelBatch(string $batchId): array
    {
        $url = $this->endpoint('batches', '/' . $batchId . '/cancel');
        $options = [];

        if($this->options){
            $options['body'] = $this->options;
        }

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        return $this->send('POST', $url, $options);
    }

    /**
     * Build per-request headers, merging in any active beta feature flags.
     * 
     * @return void
     */
    private function buildHeaders(): void
    {
        if ($this->betaFeatures !== null) {
            $this->headers['anthropic-beta'] = $this->betaFeatures;
        }
    }
}