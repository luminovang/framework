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

use \CurlFile;
use \Luminova\Base\AI;
use \App\Config\AI as AIConfig;
use \Luminova\Storage\Filesystem;
use \Luminova\Exceptions\{AIException, RuntimeException};
use function \Luminova\Funcs\{root, make_dir, absolute_url, write_content};

/**
 * OpenAI client for the Luminova AI manager.
 *
 * Implements: chat and generation, embeddings, images, TTS, audio transcription, fine-tuning, and
 * file management.
 *
 * @link https://luminova.ng/docs/0.0.80/ai-client/openai
 */
class OpenAI extends AI
{
    /**
     * {@inheritDoc}
     */
    protected array $endpoints = [
        'responses'  => 'responses',
        'models'     => 'models',
        'images'     => 'images',
        'speech'     => 'audio/speech',
        'transcribe' => 'audio/transcriptions',
        'embeddings' => 'embeddings',
        'fineTune'   => 'fine_tuning/jobs',
        'files'      => 'files',
    ];

    /**
     * Cached CurlFile instance for the most-recently used file path.
     *
     * Avoids re-creating the same `CurlFile` object on repeated calls
     * with the same file.
     *
     * @var CurlFile|null $fileInstance
     */
    private ?CurlFile $fileInstance = null;

    /**
     * Path of the file behind `$fileInstance`.
     *
     * @var string $lastFilename
     */
    private string $lastFilename = '';

    /**
     * Create a new OpenAI client instance.
     *
     * @param string|null $baseUrl API base URL (default: https://api.openai.com/v1/).
     * @param string $apiKey OpenAI secret API key (`sk-…`).
     * @param string|null $organization Optional organization ID (`org-…`).
     * @param string|null $project Optional project ID.
     * 
     * @see \App\Config\AI For default application AI configuration.
     *
     * @example - Example:
     * ```php
     * $client = new OpenAI(apiKey: env('OPENAI_KEY'), organization: 'org-abc123');
     * $reply  = $client->message('Hello, OpenAI!');
     * ```
     */
    public function __construct(
        ?string $baseUrl = null,
        ?string $apiKey = null,
        ?string $organization = null,
        ?string $project = null
    ) 
    {
        $this->options['echo'] = false;
        $headers = [];

        if ($organization !== null) {
            $headers['OpenAI-Organization'] = $organization;
        }

        if ($project !== null) {
            $headers['OpenAI-Project'] = $project;
        }

        $baseUrl ??= 'https://api.openai.com/v1/';

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
            $config->organization,
            $config->project,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function chat(array|string $messages, array $options = []): array
    {
        $url = $this->endpoint('responses');
        $options['model'] ??= $this->model ?? 'gpt-4.1-mini';
        $options['input']  = $messages;

        $this->parseOptions($options);
        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        return $this->send('POST', $url, $options)['output'] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function webSearch(string $query, array $options = []): array
    {
        $options['tools'][] = [
            'type' => 'web_search_preview'
        ];

        return $this->chat($query, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function embed(string|array $input, array $options = []): array
    {
        $url = $this->endpoint('embeddings');

        $options['model'] ??= $this->model ?? 'text-embedding-3-small';
        $options['encoding_format'] ??= 'float';
        $options['input']             = $input;

        $this->parseOptions($options);
        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        $content = $this->send('POST', $url, $options);

        $items = $content['data'] ?? [];

        if ($items === []) {
            return [];
        }

        // Sort by index to guarantee ordering matches the input order.
        usort($items, static fn(array $a, array $b) => $a['index'] <=> $b['index']);

        $vectors = array_column($items, 'embedding');

        // Return a flat vector when the caller passed a single string.
        return is_string($input) ? ($vectors[0] ?? []) : $vectors;
    }

    /**
     * {@inheritdoc}
     */
    public function image(string $prompt, array $options = []): array|bool
    {
        $suffix = 'generations';

        $options['response_format'] ??= 'url';
        $options['size']            ??= '1024x1024';
        $options['model']           ??= $this->model ?? 'gpt-image-1';
        $options['prompt']            = $prompt;

        if(isset($options['edits'])){
            $suffix = 'edits';
            $isImage = isset($options['image']);
            $isMask  = isset($options['mask']);

            $this->validateImage($options, $isImage, $isMask);

            if ($isImage) {
                $options['image'] = new CurlFile($options['image']);
            }

            if ($isMask) {
                $options['mask'] = new CurlFile($options['mask']);
            }
        }

        $this->parseOptions($options);

        $url = $this->endpoint('images', $suffix);
        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        $content = $this->send('POST', $url, $options);

        return $content['data'] ?? false;
    }

    /**
     * {@inheritdoc}
     */
    public function speech(string $text, array $options = []): string|bool
    {
        $url = $this->endpoint('speech');

        $options['voice']           ??= 'alloy';
        $options['speed']           ??= 1.0;
        $options['model']           ??= $this->model ?? 'gpt-4o-mini-tts';
        $options['response_format'] ??= $options['format'] ?? 'mp3';
        $options['input']             = $text;

        unset($options['format']);

        $this->parseOptions($options);
        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        $res = $this->send('POST', $url, $options);
        $content = $res['data'] ?? $res;

        if (!$content || !is_string($content)) {
            return false;
        }

        $path  = rtrim($this->options['path'] ?? root('writeable/ai/speech'), TRIM_DS);
        $filename = uniqid() . '.' . $this->options['response_format'];
        $destination = $path . DIRECTORY_SEPARATOR . $filename;

        if (!write_content($destination, $content)) {
            return false;
        }

        if (isset($this->options['symlink'])) {
            $symlink = rtrim($this->options['symlink'], TRIM_DS) . DIRECTORY_SEPARATOR . $filename;
            Filesystem::symbolic($destination, $symlink);
            return absolute_url($symlink);
        }

        return absolute_url($destination);
    }

    /**
     * {@inheritdoc}
     */
    public function audio(string $prompt, string $filename, array $options = []): string|bool
    {
        if (!file_exists($filename)) {
            self::error("Audio file does not exist: {$filename}");
        }

        if (!filesize($filename)) {
            self::error('Invalid or empty audio file.');
        }

        if ($this->fileInstance === null || $this->lastFilename !== $filename) {
            $this->fileInstance = new CurlFile($filename);
            $this->lastFilename = $filename;
        }

        $url = $this->endpoint('transcribe');

        $options['file']            = $this->fileInstance;
        $options['prompt']          = $prompt;
        $options['model']           ??= $this->model ?? 'whisper-1';
        $options['language']        ??= '';
        $options['temperature']     ??= 0;
        $options['response_format'] ??= $options['format'] ?? 'json';

        unset($options['format']);
        $this->parseOptions($options);
        $this->headers['Content-Type'] = 'multipart/form-data';

        $options = [
            'body' => $this->options,
            'headers' => $this->headers
        ];

        $res = $this->send('POST', $url, $options);

        return $res['text'] ?? false;
    }

    /**
     * {@inheritdoc}
     */
    public function fineTune(string $model, string $dataset, array $options = []): array
    {
        $url = $this->endpoint('fineTune');

        $isFile = is_file($dataset);
        $isAutoDelete = isset($options['auto_delete_file']);

        $fileId = $isFile ? $this->upload($dataset) : $dataset;

        if (!$fileId) {
            throw new RuntimeException("Dataset is empty or could not be uploaded: {$dataset}");
        }

        $options['training_file'] = $fileId;
        $options['suffix'] = $model;
        $options['model'] ??= $this->model ?? 'gpt-4.1-mini';

        unset($options['auto_delete_file']);
        $this->parseOptions($options);
        $options = [
            'body' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        $res = $this->send('POST', $url, $options);

        if ($isFile && $isAutoDelete) {
            @unlink($dataset);
        }

        return $res;
    }

    /**
     * {@inheritdoc}
     */
    public function fineTuneDataset(array $dataset, array $options = []): array
    {
        $path = root('writeable/ai/training/');

        if(make_dir($path)){
            throw new AIException("Failed to write to: {$path}");
        }

        $name =  uniqid() . '.jsonl';
        $file = $path.$name;

        $fh  = fopen($file, 'w');

        if ($fh === false) {
            throw new RuntimeException("Failed to write dataset to: {$file}");
        }

        foreach ($dataset as $example) {
            $line = [
                'messages' => [
                    ['role' => 'user', 'content' => $example['input']  ?? ''],
                    ['role' => 'assistant', 'content' => $example['output'] ?? ''],
                ],
            ];
            fwrite($fh, json_encode($line) . "\n");
        }

        fclose($fh);

        $options['model'] ??= $this->model ?? 'gpt-4.1-mini';
        $options['auto_delete_file'] = true;

        return $this->fineTune($options['suffix'] ?? $options['name'] ?? '', $file, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function fineTuneStatus(string $jobId): array
    {
        $url = $this->endpoint('fineTune', '/' . $jobId);

        $options = [
            'query' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        $res = $this->send('GET', $url, $options);

        return $res;
    }

    /**
     * Upload a local file to OpenAI's file store.
     *
     * Returns the file ID string on success, which can be used as the
     * `$dataset` argument in `fineTune()`.
     *
     * @param string $filename Absolute path to the local file.
     * @param string $purpose  Upload purpose. Accepted values: `fine-tune`, `assistants`,
     *                         `batch`, `vision`. Default `fine-tune`.
     * @param array $options  Additional request parameters.
     *
     * @return string|null Uploaded file ID, or `null` on failure.
     * @throws RuntimeException If the file does not exist or is empty.
     *
     * @example - Example:
     * ```php
     * $fileId = $client->upload('/path/to/training.jsonl');
     * $job    = $client->fineTune('my-model', $fileId);
     * ```
     */
    public function upload(string $filename, string $purpose = 'fine-tune', array $options = []): ?string
    {
        if (!file_exists($filename)) {
            throw new RuntimeException("File does not exist: {$filename}");
        }

        if (!filesize($filename)) {
            self::error('File is empty or unreadable.', 204);
        }

        if ($this->fileInstance === null || $this->lastFilename !== $filename) {
            $this->fileInstance = new CurlFile($filename);
            $this->lastFilename = $filename;
        }

        $url = $this->endpoint('files');

        $options['purpose'] = $purpose ?: 'fine-tune';
        $options['file'] = $this->fileInstance;

        $this->headers['Content-Type'] = 'multipart/form-data';

        $this->parseOptions($options);
        $options = [
            'headers' => $this->headers,
            'query' => $this->options,
        ];

        $res = $this->send('POST', $url, $options);

        return $res['id'] ?? null;
    }

    /**
     * List all files uploaded to the OpenAI file store.
     *
     * @param array $options Optional query parameters (e.g. `['purpose' => 'fine-tune']`).
     *
     * @return array Array of file objects.
     *
     * @example - Example:
     * 
     * ```php
     * $files = $client->files(['purpose' => 'fine-tune']);
     * foreach ($files as $file) {
     *     echo $file['id'] . ' — ' . $file['filename'] . PHP_EOL;
     * }
     * ```
     */
    public function files(array $options = []): array
    {
        $url  = $this->endpoint('files');
        $options = [
            'query' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        return $this->send('GET', $url, $options)['data'] ?? [];
    }

    /**
     * Retrieve metadata for a single uploaded file.
     *
     * @param string $fileId File ID returned by `upload()`.
     * @param array  $options Optional query parameters.
     *
     * @return array File metadata object.
     *
     * @example - Example:
     * ```php
     * $meta = $client->file('file-abc123');
     * echo $meta['filename']; // training.jsonl
     * ```
     */
    public function file(string $fileId, array $options = []): array
    {
        $url = $this->endpoint('files', '/' . $fileId);
        $options = [
            'query' => $this->options,
        ];

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        return $this->send('GET', $url, $options);
    }

    /**
     * Download the raw content of an uploaded file.
     *
     * When `$saveTo` is provided, the content is written to that path and
     * `true` is returned on success. When omitted, the raw content string
     * is returned directly.
     *
     * @param string  $fileId  File ID.
     * @param string|null $saveTo  Optional absolute path to save the file content.
     *
     * @return string|bool Raw content string, `true` if saved to disk, or `false` on failure.
     *
     * @example - Example:
     * ```php
     * // Stream to string
     * $content = $client->download('file-abc123');
     *
     * // Save to disk
     * $ok = $client->download('file-abc123', '/tmp/downloaded.jsonl');
     * ```
     */
    public function download(string $fileId, ?string $saveTo = null): string|bool
    {
        $url = $this->endpoint('files', '/' . $fileId . '/content');
        $content = $this->http->request('GET', $url)->getContents();

        if ($saveTo !== null) {
            return write_content($saveTo, $content);
        }

        return $content;
    }

    /**
     * Delete an uploaded file from the OpenAI file store.
     *
     * @param string $fileId File ID to delete.
     *
     * @return bool Returns `true` if the file was successfully deleted.
     *
     * @example
     * ```php
     * $deleted = $client->delete('file-abc123');
     * ```
     */
    public function delete(string $fileId): bool
    {
        $url  = $this->endpoint('files', '/' . $fileId);
        $options = [];

        if($this->options){
            $options['body'] = $this->options;
        }

        if($this->headers){
            $options['headers'] = $this->headers;
        }

        $data = $this->send('DELETE', $url, $options);

        return (bool) ($data['deleted'] ?? false);
    }

    /**
     * {@inheritdoc}
     */
    public function vision(string $prompt, string|array $images, array $options = []): array
    {
        $detail = $options['detail'] ?? 'auto';
        $messages = [];
 
        unset($options['detail']);
 
        foreach ((array) $images as $image) {
            if (is_array($image)) {
                $messages[] = $image;
                continue;
            }

            if (filter_var($image, FILTER_VALIDATE_URL)) {
                $messages[] = [
                    'type'      => 'input_image',
                    'image_url' => $image,
                    'detail'    => $detail,
                ];

                continue;
            }

            if (!file_exists($image)) {
                throw new RuntimeException("Vision image file does not exist: {$image}");
            }

            $raw  = file_get_contents($image);

            if ($raw === false) {
                throw new RuntimeException("Vision image file is not readable: {$image}");
            }

            $extension = strtolower(pathinfo($image, PATHINFO_EXTENSION));
            $mediaType = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png'         => 'image/png',
                'gif'         => 'image/gif',
                'webp'        => 'image/webp',
                default       => 'image/jpeg',
            };

            $messages[] = [
                'type'      => 'input_image',
                'image_url' => "data:{$mediaType};base64," . base64_encode($raw),
                'detail'    => $detail,
            ];
        }
 
        $messages[] = ['type' => 'input_text', 'text' => $prompt];
        $options['model'] ??= $this->model ?? 'gpt-4.1-mini';
 
        return $this->chat(['role' => 'user', 'content' => $messages], $options);
    }

    /**
     * Validate image and mask files before sending to the edit endpoint.
     *
     * @param array $options Request options potentially containing `image` and `mask` paths.
     * @param bool $isImage Whether the `image` key is set.
     * @param bool $isMask  Whether the `mask` key is set.
     *
     * @throws RuntimeException If a file is missing, empty, or exceeds the 4 MB limit.
     */
    private function validateImage(array $options, bool $isImage, bool $isMask): void
    {
        if ($isImage) {
            if (!file_exists($options['image'])) {
                self::error("Source image does not exist: {$options['image']}", 204);
            }

            $size = filesize($options['image']);
            if (!$size || $size > 4 * 1024 * 1024) {
                self::error('Source image is empty or exceeds the 4 MB limit.', 204);
            }
        }

        if ($isMask) {
            if (!file_exists($options['mask'])) {
                self::error("Mask image does not exist: {$options['mask']}", 204);
            }

            $size = filesize($options['mask']);

            if (!$size || $size > 4 * 1024 * 1024) {
                self::error('Mask image is empty or exceeds the 4 MB limit.', 204);
            }
        }
    }

    /**
     * Throw a `RuntimeException` with the given message and code.
     *
     * @param string|null $error Error message. Defaults to a generic message.
     * @param int         $code  Exception code.
     *
     * @return never
     * @throws RuntimeException Always.
     */
    private static function error(?string $error = null, int $code = 0): never
    {
        throw new RuntimeException($error ?? 'Unable to complete request.', $code);
    }
}