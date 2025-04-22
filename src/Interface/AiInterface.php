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

use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\JsonException;

interface AiInterface
{
  /**
   * Constructs a new instance.
   *
   * @param string $apiKey The API key for OpenAI.
   * @param string $version The API version (default: `v1`).
   * @param string|null $organization The organization ID (optional).
   * @param string|null $project The project ID (optional).
   */
  public function __construct(
      string $apiKey,
      string $version = 'v1',
      ?string $organization = null,
      ?string $project = null
  );

  /**
   * Get completions for the given prompt.
   *
   * @param string $prompt The prompt for which completions are requested.
   * @param array $options Optional parameters to pass to the API.
   *                       Required options:
   *                       - `model` (string): The model to use for completion (default: `gpt-3.5-turbo-instruct`).
   *
   * @return array Returns an array of completion choices.
   * @throws RuntimeException If an error is encountered during the network request.
   * @throws JsonException If an error is encountered during JSON decoding.
   */
  public function completion(string $prompt, array $options = []): array;

  /**
   * Get suggestions for the given prompt.
   *
   * @param string $prompt The prompt for which suggestions are requested.
   * @param array $options Optional parameters to pass to the API.
   *                       Required options:
   *                       - `model` (string): The model to use for suggestions (default: `gpt-3.5-turbo-instruct`).
   *
   * @return array Returns an array of suggestion choices.
   * @throws RuntimeException If an error is encountered during the network request.
   * @throws JsonException If an error is encountered during JSON decoding.
   */
  public function suggestions(string $prompt, array $options = []): array;

  /**
   * Send a message and receive replies.
   *
   * @param string $prompt The message prompt to send.
   * @param array $options Optional parameters to pass to the API.
   *                       Required options:
   *                       - `model` (string): The model to use for the message (default: `gpt-3.5-turbo-instruct`).
   *
   * @return array Returns an array of replies.
   * @throws RuntimeException If an error is encountered during the network request.
   * @throws JsonException If an error is encountered during JSON decoding.
   */
  public function message(string $prompt, array $options = []): array;

  /**
   * Generate random images.
   *
   * @param string $prompt The message prompt to send.
   * @param array $options Optional parameters to pass to the API.
   *                       Required options:
   *                       - `model` (string): The model to use for image generation (default: `dall-e-3`).
   *                       - `response_format` (string): The format of the generated image (default: `url`).
   *
   * @return array|false An array of image URLs or base64 encoded images, false on failure.
   * @throws RuntimeException If an error is encountered during the network request.
   * @throws JsonException If an error is encountered during JSON decoding.
   */
  public function image(string $prompt, array $options = []): array|bool;

  /**
   * Edit or swap areas of an image with another image.
   *
   * @param string $prompt The message prompt to send.
   * @param array $options Optional parameters to pass to the API.
   *                       Required options:
   *                       - `model` (string): The model to use for image editing (default: `dall-e-2`).
   *                       - `image` (string): The path to the original image to edit (PNG only, max 4MB).
   *                       - `mask` (string): The path to the image to swap areas with (PNG only, max 4MB).
   *                       - `response_format` (string): The format of the edited image (default: `url`).
   *
   * @return array|false An array of edited image URLs or base64 encoded images, false on failure.
   * @throws RuntimeException If an error is encountered during the network request.
   * @throws JsonException If an error is encountered during JSON decoding.
   */
  public function imageEdit(string $prompt, array $options): array|bool;

  /**
   * Create additional training on how ChatGPT should behave based on your training examples.
   *
   * @param string $trainingFile The path to the training file.
   * @param array $options Optional parameters to pass to the API.
   *                       Required options:
   *                       - `model` (string): The model to use for fine-tuning (default: `gpt-3.5-turbo`).
   *
   * @return array Returns an array of training responses.
   * @throws RuntimeException If an error is encountered during the network request.
   * @throws JsonException If an error is encountered during JSON decoding.
   */
  public function fineTune(string $trainingFile, array $options = []): array;

  /**
   * Get text embeddings to measure the relatedness of text strings.
   *
   * @param string|array $input The text or array of text to embed.
   * @param array $options Optional parameters to pass to the API.
   *                       Required options:
   *                       - `model` (string): The model to use for embeddings (default: `text-embedding-ada-002`).
   *
   * @return array Returns an array of embedding vectors.
   * @throws RuntimeException If an error is encountered during the network request.
   * @throws JsonException If an error is encountered during JSON decoding.
   */
  public function embed(string|array $input, array $options = []): array;

  /**
   * Generate audio from text.
   *
   * @param string $text The text to convert.
   * @param array $options Additional options for conversion.
   *                       Available options:
   *                       - `model` (string): The model to use for conversion (default: `tts-1`).
   *                       - `voice` (string): The voice to use for conversion (default: `alloy`), available voices: `alloy`, `echo`, `fable`, `onyx`, `nova`, or `shimmer`.
   *                       - `path` (string): The destination path to save the converted file (default: `writeable/ai/speech`).
   *                       - `response_format` (string): The format of the converted file (default: `mp3`).
   *                       - `symlink` (string): Optional symbolic link destination.
   *
   * @return string|false Return file url on success, false on failure.
   * @throws RuntimeException If an error is encountered during the network request.
   * @throws JsonException If an error is encountered during JSON decoding.
   */
  public function speech(string $text, array $options = []): string|bool;

  /**
   * Translates audio to text.
   *
   * @param string $filename The path to the audio file.
   * @param array $options Additional options for conversion.
   *                       Available options:
   *                       - `model` (string): The model to use for conversion (default: `whisper-1`).
   *                       - `response_format` (string): The format of the converted file (default: `json`).
   *
   * @return string|false Returns the converted audio on success, false on failure.
   * @throws RuntimeException If an error is encountered during the network request.
   * @throws JsonException If an error is encountered during JSON decoding.
   */
  public function audio(string $filename, array $options = []): string|bool;

  /**
   * Retrieve a list of available models.
   *
   * @param string|null $name The name of the model to retrieve (optional).
   *
   * @return array An array containing information about the model(s).
   * @throws RuntimeException If an error is encountered during the network request.
   * @throws JsonException If an error is encountered during JSON decoding.
   */
  public function models(?string $name = null): array;
}