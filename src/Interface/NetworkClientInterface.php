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

use \Luminova\Http\Message\Response;
use \Psr\Http\Client\ClientInterface;
use \Psr\Http\Message\ResponseInterface;

interface NetworkClientInterface 
{
    /**
     * Curl client constructor.
     * 
     * @param array $config The client connection configuration.
     * 
    */
    public function __construct(array $config = []);

    /**
     * Retrieve the client object.
     * This can be either PSR client interface NetworkClientInterface 
     * 
     * @return ClientInterface<\T>|NetworkClientInterface<\T>|null Return client object, otherwise null.
    */
    public function getClient(): ClientInterface|self|null;

    /**
     * Retrieve configuration option from client object.
     * 
     * @param string|null $option The option name to return (default: null).
     * 
     * @return mixed Return configuration option based on option name, or return all if option is null, otherwise null.
    */
    public function getConfig(?string $option = null): mixed;
    
    /**
     * Send an HTTP request to a url.
     *
     * @param string $method The request method.
     * @param string $url The URL to send the request.
     * @param array<string,mixed> $options The headers to send with the request.
     *
     * @return ResponseInterface|Response Return request response object.
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface|Response;
} 