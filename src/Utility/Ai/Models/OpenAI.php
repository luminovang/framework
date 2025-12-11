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
namespace Luminova\Utility\Ai\Models;

use \CurlFile;
use \Throwable;
use \Luminova\Http\Client\Novio;
use \Luminova\Utility\Storage\Filesystem;
use \Luminova\Interface\{AIModelInterface, LazyObjectInterface};
use function \Luminova\Funcs\{root, absolute_url, write_content};
use \Luminova\Exceptions\{AppException, JsonException, RuntimeException};

class OpenAI implements AIModelInterface, LazyObjectInterface
{
    /**
     * @var Novio|null $network
     */
    private ?Novio $http = null;

    /**
     * @var string $version
     */
    private static string $version = 'v1';

    /**
     * @var string $url
     */
    private static string $url = 'https://api.openai.com/';

    /**
     * @var array $endpoints
     */
    private static array $endpoints = [
        'completions'           => '/completions',
        'chatCompletions'       => '/chat/completions',
        'speech'                => '/audio/speech',
        'transcriptions'        => '/audio/transcriptions',
        'translate'             => '/audio/translation',
        'models'                => '/models',
        'images'                => '/images/generations',
        'imageEdit'             => '/images/edits',
        'embeddings'            => '/embeddings',
        'fineTune'              => 'fine_tuning/jobs'
    ];

    /**
     * @var CurlFile|null $fileInstance
     */
    private ?CurlFile $fileInstance = null;

    /**
     * @var string $lastFilename
     */
    private string $lastFilename = '';

    /**
     * {@inheritdoc}
     */
    public function __construct(
        string $api_key, 
        string $version = 'v1', 
        ?string $organization = null, 
        ?string $project = null
    )
    {
        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ];

        if($organization !== null){
            $headers['OpenAI-Organization'] = $organization;
        }
        if($project !== null){
            $headers['OpenAI-Project'] = $project;
        }

        self::$version = $version;
        $this->http = new Novio([
            'headers' => $headers
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function models(?string $name = null): array
    {
        $url = self::getUrl('models', (($name === null) ? '' : '/' . $name));
        try {
            $content = $this->http->request('GET', $url)->getContents();
            $content = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if(isset($content['error'])){
                self::error($content['error']['message'] ?? null);
            }

            if($name === null){
                return $content['data'] ?? [];
            }

            return $content;
        } catch (Throwable $e) {
            $this->eHandler($e);
        } 

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function completion(string $prompt, array $options = []): array
    {
        $url = self::getUrl('completions');
        try {
            if(isset($options['__suggestions'])){
                $options['stop'] = ['\n'];
                unset($options['__suggestions']);
                $options = $this->getParams($prompt, $options, false);
            }else{
                $options = $this->getParams($prompt, $options);
            }

            $content = $this->http->request('POST', $url, [
                'body' => $options
            ])->getContents();
            $content = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if(isset($content['error'])){
                self::error($content['error']['message'] ?? null);
            }

            return $content['choices'] ?? [];
        } catch (Throwable $e) {
            $this->eHandler($e);
        } 

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function suggestions(string $prompt, array $options = []): array
    {
        $options['__suggestions'] = true;
        return $this->completion($prompt, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function message(string $prompt, array $options = []): array
    {
        $url = self::getUrl('chatCompletions');
        try {
            $content = $this->http->request('POST', $url, [
                'body' => $this->getParams($prompt, $options, true)
            ])->getContents();
            $content = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if(isset($content['error'])){
                self::error($content['error']['message'] ?? null);
            }

            $replies = [];
            foreach ($content['choices'] as $choice) {
                $replies[] = $choice['message'];
            }

            return $replies;
        } catch (Throwable $e) {
            $this->eHandler($e);
        } 

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function embed(string|array $input, array $options = []): array
    {
        $url = self::getUrl('embeddings');
        try {
            $content = $this->http->request('POST', $url, [
                'body' => [
                    'model' => $options['model'] ?? 'text-embedding-ada-002',
                    'dimensions' => $options['dimensions'] ?? '',
                    'encoding_format' => $options['encoding_format'] ?? 'float',
                    'user' => $options['user'] ?? '',
                    'input' => $input
                ]
            ])->getContents();
            $content = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if(isset($content['error'])){
                self::error($content['error']['message'] ?? null);
            }

            return $content['data'] ?? [];
        } catch (Throwable $e) {
            $this->eHandler($e);
        } 

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function fineTune(string $trainingFile, array $options = []): array
    {
        $url = self::getUrl('fineTune');
        try {
            $content = $this->http->request('POST', $url, [
                'body' => [
                    'model' => $options['model'] ?? 'gpt-3.5-turbo',
                    'training_file' => $trainingFile,
                    'validation_file' => $options['validation_file'] ?? ''
                ]
            ])->getContents();
            $content = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if(isset($content['error'])){
                self::error($content['error']['message'] ?? null);
            }

            return $content;
        } catch (Throwable $e) {
            $this->eHandler($e);
        } 

        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function image(string $prompt, array $options = []): array|bool
    {
        $url = self::getUrl('images');
        try {
            $content = $this->http->request('POST', $url, [
               'body' => [
                    'model' => $options['model'] ?? 'dall-e-3',
                    'size' => $options['size'] ?? '1024x1024',
                    'n' => $options['n'] ?? 1,
                    'response_format' => $options['response_format'] ?? 'url',
                    'user' => $options['user'] ?? '',
                    'prompt' => $prompt
               ]
            ])->getContents();

            if(isset($content['error'])){
                self::error($content['error']['message'] ?? null);
            }

            return $content['data'] ?? false;
        } catch (Throwable $e) {
            $this->eHandler($e);
        } 

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function imageEdit(string $prompt, array $options): array|bool
    {
        if (!file_exists($options['image'])) {
            self::error("Image: {$options['image']} does not exist", 204);
        }

        if (!file_exists($options['mask'])) {
            self::error("Image mask: {$options['mask']} does not exist", 204);
        }

        $imageSize = filesize($options['image']);
        if (!$imageSize || $imageSize > (4 * 1024 * 1024)) {
            self::error('Invalid image size or corrupted, or image is too large (max allowed size is 4MB)', 204);
        }

        $maskSize = filesize($options['mask']);
        if (!$maskSize || $maskSize > (4 * 1024 * 1024)) {
            self::error('Invalid mask size or corrupted, or mask is too large (max allowed size is 4MB)', 204);
        }

        if (strtolower(pathinfo($options['image'], PATHINFO_EXTENSION)) !== 'png') {
            self::error('Unsupported image type, only PNG image is allowed', 204);
        }

        if (strtolower(pathinfo($options['mask'], PATHINFO_EXTENSION)) !== 'png') {
            self::error('Unsupported image mask type, only PNG image is allowed', 204);
        }

        $url = self::getUrl('imageEdit');
        try {
            $content = $this->http->request('POST', $url, [
                'body' => [
                    'model' => $options['model'] ?? 'dall-e-2',
                    'image' => new CurlFile($options['image']),
                    'mask' => new CurlFile($options['mask']),
                    'n' => $options['n'] ?? 1,
                    'size' => $options['size'] ?? '1024x1024',
                    'response_format' => $options['response_format'] ?? 'url',
                    'user' => $options['user'] ?? '',
                    'prompt' => $prompt
                ]
            ])->getContents();

            if (isset($content['error'])) {
                self::error($content['error']['message'] ?? null);
            }

            return $content['data'] ?? false;
        } catch (Throwable $e) {
            $this->eHandler($e);
        } 

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function speech(string $text, array $options = []): string|bool
    {
        $url = self::getUrl('speech');
        try {
            $content = $this->http->request('POST', $url, [
                'body' => [
                    'model' => $options['model'] ?? 'tts-1',
                    'voice' => $options['voice'] ?? 'alloy',
                    'response_format' => $options['format'] ?? 'mp3',
                    'speed' => $options['speed'] ?? 1.0,
                    'input' => $text
                ]
            ])->getContents();

            if(isset($content['error'])){
                self::error($content['error']['message'] ?? null);
            }

            $path = rtrim($options['path'] ?? root('writeable/ai/speech'), TRIM_DS);
            $filename = uniqid() . '.' . ($options['response_format'] ?? 'mp3');
            $destination = $path . DIRECTORY_SEPARATOR . $filename;
            
            if(write_content($destination, $content)){
                if(isset($options['symlink'])){
                    $symlink = rtrim($options['symlink'], TRIM_DS) . DIRECTORY_SEPARATOR . $filename;
                    Filesystem::symbolic($destination, $symlink);

                    return absolute_url($symlink);
                }

                return absolute_url($destination);
            }

            return false;
        } catch (Throwable $e) {
            $this->eHandler($e);
        } 

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function audio(string $filename, array $options = []): string|bool
    {
        if(!file_exists($filename)){
            self::error('File: '  . $filename . ' does not exist', 204);
        }

        $fileSize = filesize($filename);

        if(!$fileSize){
            self::error('Invalid file size or corrupted file', 204);
        }

        if ($fileSize > (25 * 1024 * 1024)) {
            self::error('File is too large, maximum allowed size is 25MB', 204);
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if(!in_array($extension, ['mp3', 'mp4','mpeg','mpga','m4a','wav','webm'])){
            self::error('Unsupported file type, allowed files are [mp3, mp4, mpeg, mpga, m4a, wav, or webm]', 204);
        }

        if($this->fileInstance === null || $this->lastFilename !== $filename){
            $this->fileInstance = new CurlFile($filename);
            $this->lastFilename = $filename;
        }

        $url = self::getUrl('transcriptions');
        try {
            $content = $this->http->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'multipart/form-data'
                ],
                'body' => [
                    'model' => $options['model'] ?? 'whisper-1',
                    'prompt' => $options['prompt'] ?? '',
                    'language' => $options['language'] ?? '',
                    'response_format' => $options['response_format'] ?? 'json',
                    'temperature' => $options['temperature'] ?? 0,
                    'file' => $this->fileInstance
                ]
            ])->getContents();
            $content = json_decode($content, true);

            if(isset($content['error'])){
                self::error($content['error']['message'] ?? null);
            }

            return $content['text'] ?? false;
        } catch (Throwable $e) {
            $this->eHandler($e);
        } 

        return false;
    }

    /**
     * Get the URL for the specified endpoint.
     *
     * @param string $endpoint The endpoint name.
     * @param string $suffix Suffix for the endpoint.
     * 
     * @return string The complete URL for the endpoint.
     * @throws RuntimeException When the endpoint is not available or not implemented.
     */
    private static function getUrl(string $endpoint, string $suffix = ''): string 
    {
        if(isset(self::$endpoints[$endpoint])){
            return self::$url . self::$version . self::$endpoints[$endpoint] . $suffix;
        }

        throw new RuntimeException('The endpoint: ' . $endpoint . ' is not available or not implemented yet.', 501);
    }

    /**
     * Throw an exception.
     * 
     * @param string|null $error The exception message.
     * @param int $code The exception code (default: `202`).
     * 
     * @throws RuntimeException
     */
    private static function error(?string $error = null, int $code = 202): void 
    {
        throw new RuntimeException($error ?? 'Unable complete request', $code);
    }

    /**
     * Throw an exception.
     * 
     * @param Throwable $e The exception object.
     * 
     * @throws RuntimeException
     * @throws JsonException
     */
    private function eHandler(Throwable $e): void 
    {
        if($e instanceof AppException){
            throw $e;
        }

        if($e instanceof \JsonException){
            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }

        throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
    } 

    /**
     * Generates the parameters for an API request based on the given prompt and options.
     *
     * @param string $prompt The input prompt for the API.
     * @param array<string,mixed> $options Optional parameters to customize the request.
     *
     *     @type int    max_tokens  The maximum number of tokens to generate. Default is 50.
     *     @type float  temperature Sampling temperature, between 0 and 1. Default is 0.7.
     *     @type float  top_p       Nucleus sampling probability, between 0 and 1. Default is 0.1.
     *     @type string user        A unique identifier for the user. Default is an empty string.
     *     @type int    n           Number of completions to generate. Default is 1.
     *     @type string model       The model to use for generating completions. Default is 'gpt-3.5-turbo-instruct'.
     *     @type mixed  stop        Sequence where the API will stop generating further tokens. Optional.
     *     @type bool  echo         Return back the prompt with generated response.
     * 
     * @param bool $isChat Whether the request is for a chat-based model. Default is false.
     * 
     * @return array Return the parameters formatted for the API request.
     */
    private function getParams(string $prompt, array $options = [], bool $isChat = false): array 
    {
        $defaults = [
            'max_tokens' => $options['max_tokens'] ?? 50,
            'temperature' => $options['temperature'] ?? 0.7,
            'top_p' => $options['top_p'] ?? 0.1,
            'user' => $options['user'] ?? '',
            'n' => $options['n'] ?? 1,
        ];

        if (isset($options['stop'])) {
            $defaults['stop'] = $options['stop'];
        }

        if ($isChat) {
            return [
                'model' => $options['model'] ?? 'gpt-3.5-turbo-instruct',
                'messages' => [
                    'role' => 'user',
                    'content' => $prompt
                ],
                $defaults
            ];
        } 

        return [
            'model' => $options['model'] ?? 'gpt-3.5-turbo-instruct',
            'echo' => $options['echo'] ?? false,
            'prompt' => $prompt
        ];
    }
}