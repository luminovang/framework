<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Interface;

use \GuzzleHttp\Promise\PromiseInterface;
use \GuzzleHttp\Psr7\Request as GuzzleRequest;

interface AsyncClientInterface
{
    public function sendAsync(GuzzleRequest $request): PromiseInterface;
}