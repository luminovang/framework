<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Exceptions;

use \Luminova\Exceptions\AppException;
use \Throwable;

class ViewNotFoundException extends AppException
{
    /**
     * Constructor for ViewNotFoundException.
     *
     * @param string     $view   The exception view
     * @param int        $code      The exception code (default: 500).
     * @param Throwable $previous  The previous exception if applicable (default: null).
     */
    public function __construct(string $view = 'home', int $code = 500, Throwable $previous = null)
    {
        $message = sprintf('The view "%s" could not be found in the view directory "/resources/views/".', $view);
        parent::__construct($message, $code, $previous);
    }
}