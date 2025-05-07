<?php
/**
 * Luminova Framework Request HTTP Methods.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http;

class Method
{
    /** 
     * HTTP ANY request method: 
     * 
     * Used in routing system to allow any incoming request method.
     * 
     * @var string ANY
     * 
     * > **Note:** This is not a standard HTTP method.
     */
    public const ANY = 'ANY';

    /** 
     * HTTP GET method: Used to retrieve data from the server without modifying it.
     * 
     * @var string GET
     */
    public const GET = 'GET';

    /** 
     * HTTP POST method: Used to submit data to the server for processing (e.g., form submission, creating resources).
     * 
     * @var string POST
     */
    public const POST = 'POST';

    /** 
     * HTTP PUT method: Used to fully update or replace an existing resource.
     * 
     * @var string PUT
     */
    public const PUT = 'PUT';

    /** 
     * HTTP DELETE method: Used to remove a resource from the server.
     * 
     * @var string DELETE
     */
    public const DELETE = 'DELETE';

    /** 
     * HTTP OPTIONS method: Used to describe communication options for the target resource.
     * 
     * @var string OPTIONS
     */
    public const OPTIONS = 'OPTIONS';

    /** 
     * HTTP PATCH method: Used for partial updates to an existing resource.
     * 
     * @var string PATCH
     */
    public const PATCH = 'PATCH';

    /** 
     * HTTP HEAD method: Similar to GET, but only returns response headers (no body).
     * 
     * @var string HEAD
     */
    public const HEAD = 'HEAD';

    /** 
     * HTTP CONNECT method: Used for establishing a tunnel to a server (e.g., HTTPS via a proxy).
     * 
     * @var string CONNECT
     */
    public const CONNECT = 'CONNECT';

    /** 
     * HTTP TRACE method: Used for diagnostic purposes, returning the request received by the server.
     * 
     * @var string TRACE
     */
    public const TRACE = 'TRACE';

    /** 
     * WebDAV PROPFIND method: Retrieves properties of a resource (used in WebDAV).
     * 
     * @var string PROPFIND
     */
    public const PROPFIND = 'PROPFIND';

    /** 
     * WebDAV MKCOL method: Creates a new collection (folder) at the specified location.
     * 
     * @var string MKCOL
     */
    public const MKCOL = 'MKCOL';

    /** 
     * WebDAV COPY method: Copies a resource from one location to another.
     * 
     * @var string COPY
     */
    public const COPY = 'COPY';

    /** 
     * WebDAV MOVE method: Moves a resource from one location to another.
     * 
     * @var string MOVE
     */
    public const MOVE = 'MOVE';

    /** 
     * WebDAV LOCK method: Locks a resource to prevent modification by others.
     * 
     * @var string LOCK
     */
    public const LOCK = 'LOCK';

    /** 
     * WebDAV UNLOCK method: Unlocks a previously locked resource.
     * 
     * @var string UNLOCK
     */
    public const UNLOCK = 'UNLOCK';

    /** 
     * List of all standard HTTP methods.
     * 
     * @var array<int,string> METHODS
     */
    public const METHODS = [
        self::GET, self::POST, self::PUT, self::DELETE, self::OPTIONS,
        self::PATCH, self::HEAD, self::CONNECT, self::TRACE, self::PROPFIND,
        self::MKCOL, self::COPY, self::MOVE, self::LOCK, self::UNLOCK
    ];
}