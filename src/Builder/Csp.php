<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Builder;

class Csp 
{
    /**
     * @var string|null $nonce 
    */
    private static ?string $nonce = null;

    /**
     * Get generated nonce string.
     * 
     * @return string Return generated script nonce.
    */
    public function getNonce(): string
    {
        if (static::$nonce === null) {
            static::$nonce = 'nonce-' . bin2hex(random_bytes(12));
        }

        return static::$nonce;
    }
}