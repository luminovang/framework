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
if (PHP_SAPI === 'cli') {
    return;
}

$_SERVER['SCRIPT_NAME'] = DIRECTORY_SEPARATOR  . 'index.php';
$_SERVER['SERVER_SOFTWARE'] = 'NovaKit/ (Luminova) PHP/' . PHP_VERSION. ' (Development Server)';
$_SERVER['NOVAKIT_EXECUTION_ENV'] = dirname($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR . 'php novakit';

// If $_LUMINOVA_PATH is an existing file or folder within the public folder handle request
$_LUMINOVA_URL = urldecode(parse_url('https://luminova.ng' . $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$_LUMINOVA_PATH = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . ltrim($_LUMINOVA_URL, '/\\');

if ($_LUMINOVA_URL !== '/' && (is_file($_LUMINOVA_PATH) || is_dir($_LUMINOVA_PATH))) {
    return false;
}

require_once $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'index.php';