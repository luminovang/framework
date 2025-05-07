<?php 
/**
 * Luminova Framework Mod-Rewrite Front Controller.
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
$_SERVER['FRAMEWORK_VERSION'] = getenv('FRAMEWORK_VERSION') ?: '3.5.6';
$_SERVER['NOVAKIT_VERSION'] = getenv('NOVAKIT_VERSION') ?: '2.9.8';
$_SERVER['NOVAKIT_EXECUTION_ENV'] = dirname($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR . 'novakit';
$_SERVER['SERVER_SOFTWARE'] = sprintf(
    '(NovaKit/%s) (Luminova/%s) (PHP/%s; %s)',
    $_SERVER['NOVAKIT_VERSION'],
    $_SERVER['FRAMEWORK_VERSION'],
    PHP_VERSION,
    'Development Server'
);

// Determine the requested URL path (decoded)
$_LUMINOVA_URL = urldecode(parse_url('https://luminova.ng' . $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$_LUMINOVA_PATH = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . ltrim($_LUMINOVA_URL, '/\\');

// If the path is a real file or directory, let Apache serve it
if ($_LUMINOVA_URL !== '/' && (is_file($_LUMINOVA_PATH) || is_dir($_LUMINOVA_PATH))) {
    return false;
}

require_once $_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_NAME'];