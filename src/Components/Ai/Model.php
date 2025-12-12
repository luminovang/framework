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
namespace Luminova\Components\Ai;

use \App\Config\AI;
use \Luminova\Ai\Models\OpenAI;
use \Luminova\Interface\AIModelInterface;
use \Luminova\Exceptions\{AppException, RuntimeException};
use \Throwable;

final class Model
{
    /**
     * The AI object.
     * 
     * @var AIModelInterface|null $ai
     */
    private ?AIModelInterface $ai = null;

    /**
     * Application AI configuration.
     * 
     * @var AI|null $config
     */
    private static ?AI $config = null;

    /**
     * Initialize model instance with AI interface.
     * 
     * @param AIModelInterface|null $ai The AI object to use (default: Openai).
     */
    public function __construct(?AIModelInterface $ai = null)
    {
        self::$config ??= new AI();
        $this->ai = $ai ?? new OpenAI(
            self::$config->apiKey, 
            self::$config->version, 
            self::$config->organization, 
            self::$config->project
        );
    }

    /**
     * Get instance of the AI interface.
     * 
     * @return AIModelInterface Return the AI object.
     */
    public function getAiModel(): ?AIModelInterface 
    {
        return $this->ai;
    }

    /**
     * Call AI class methods.
     * 
     * @param string $method The name of the method.
     * @param array $arguments An array of arguments to the method.
     * 
     * @return mixed The return value of the method.
     */
    public function __call(string $method, array $arguments): mixed
    {
        try{
            return $this->ai->{$method}(...$arguments);
        }catch(Throwable $e){
            if($e instanceof AppException){
                throw $e;
            }
            
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }
}