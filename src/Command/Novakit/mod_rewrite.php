<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/

if (PHP_SAPI === 'cli') {
    return;
}

$uri = urldecode(parse_url('https://luminova.ng' . $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SERVER_SOFTWARE'] = 'NovaKit/ (Luminova) PHP/' . PHP_VERSION. ' (Development Server)';
$_SERVER['NOVAKIT_EXECUTION_ENV'] = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'php novakit';
$path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . ltrim($uri, '/');

// If $path is an existing file or folder within the public folder handle request
if ($uri !== '/' && (is_file($path) || is_dir($path))) {
    return false;
}

unset($uri, $path);

require_once $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'index.php';
