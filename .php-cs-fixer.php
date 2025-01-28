<?php 
declare(strict_types=1);
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
use \Luminova\Debugger\PhpCsFixer;

return (new PhpCsFixer(__DIR__, PhpCsFixer::FIX_FRAMEWORK))->getRules();