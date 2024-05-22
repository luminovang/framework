<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Ai;

use \Luminova\Interface\AiInterface;
use \Luminova\Ai\Models\OpenAI;
use \App\Controllers\Config\AI;
use \Luminova\Exceptions\AppException;
use \Luminova\Exceptions\RuntimeException;
use \Throwable;

final class Model
{
    /**
     * @var AiInterface|null $ai The AI object.
    */
    private ?AiInterface $ai = null;

    /**
     * Initalize model instance with AI interface.
     * 
     * @param AiInterface $ai The AI object to use (default: Openai).
    */
    public function __construct(AiInterface $ai = null)
    {
        $this->ai = ($ai ?? new OpenAI(AI::$apiKey, AI::$version, AI::$organization, AI::$project));
    }

    /**
     * Get instance of the AI interface.
     * 
     * @return AiInterface Return the AI object.
    */
    public function getAiModel(): ?AiInterface 
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
        }catch(AppException|Throwable $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }
}