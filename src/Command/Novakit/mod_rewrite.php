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

$uri = parse_url('https://nanoblocktech.com' . $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
$uri = urldecode($uri);

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['LOCAL_SERVER_INSTANCE'] = 'local.server';
$path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . ltrim($uri, '/');

// If $path is an existing file or folder within the public folder handle request
if ($uri !== '/' && (is_file($path) || is_dir($path))) {
    return false;
}

unset($uri, $path);

// handle the request from from controller index.
require_once $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'index.php';
