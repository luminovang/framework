<?php 
/**
 * Luminova Framework HTTP Development Server Mod-Rewrite.
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
$_SERVER['NOVAKIT_EXECUTION_ENV'] = dirname($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR . 'novakit';

$_SERVER['LUMINOVA_VERSION'] = getenv('LUMINOVA_VERSION') ?: '3.7.8';
$_SERVER['NOVAKIT_VERSION'] = getenv('NOVAKIT_VERSION') ?: '3.0.0';
$_SERVER['SERVER_SOFTWARE'] = sprintf(
    '(NovaKit/%s) (Luminova/%s) (PHP/%s; Development Server)',
    $_SERVER['NOVAKIT_VERSION'],
    $_SERVER['LUMINOVA_VERSION'],
    PHP_VERSION
);

$_LUMINOVA_URI = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
);

$_LUMINOVA_DOC_ROOT = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR;
$_LUMINOVA_PATH = $_LUMINOVA_DOC_ROOT . ltrim($_LUMINOVA_URI, '/\\');

if ($_LUMINOVA_URI !== '/' && file_exists($_LUMINOVA_PATH)) {
    return false;
}

require_once "{$_LUMINOVA_DOC_ROOT}index.php";