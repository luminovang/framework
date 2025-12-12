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

$NOVAKIT_VERSION = getenv('NOVAKIT_VERSION') ?: '3.0.0';
$LUMINOVA_VERSION = getenv('LUMINOVA_VERSION') ?: '3.8.5';

$NOVAKIT_SOFTWARE = sprintf(
    '(NovaKit/%s) (Luminova/%s) (PHP/%s; Development Server)',
    $NOVAKIT_VERSION,
    $LUMINOVA_VERSION,
    PHP_VERSION
);

if (PHP_SAPI === 'cli') {
    putenv('RUNTIME_ENV=novakit');
    putenv("SERVER_SOFTWARE={$NOVAKIT_SOFTWARE}");

    return;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['RUNTIME_ENV'] = 'novakit';
$_SERVER['LUMINOVA_VERSION'] = $LUMINOVA_VERSION;
$_SERVER['NOVAKIT_VERSION'] = $NOVAKIT_VERSION;
$_SERVER['SERVER_SOFTWARE'] = $NOVAKIT_SOFTWARE;

$_LUMINOVA_URI = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
);

$_LUMINOVA_DOC_ROOT = rtrim(
    $_SERVER['DOCUMENT_ROOT'],
    '/\\'
) . DIRECTORY_SEPARATOR;

$_LUMINOVA_PATH = $_LUMINOVA_DOC_ROOT . ltrim($_LUMINOVA_URI, '/\\');

unset(
    $NOVAKIT_VERSION,
    $LUMINOVA_VERSION,
    $NOVAKIT_SOFTWARE
);

if ($_LUMINOVA_URI !== '/' && file_exists($_LUMINOVA_PATH)) {
    return false;
}

require_once $_LUMINOVA_DOC_ROOT . 'index.php';