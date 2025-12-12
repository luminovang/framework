<?php
declare(strict_types=1);
/**
 * Luminova Framework HTTP Development Server Mod-Rewrite.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */

/**
 * Luminova PHP server custom request variables.
 *
 * @var array{
 *     NOVAKIT_VERSION: string,
 *     LUMINOVA_VERSION: string,
 *     SERVER_SOFTWARE: string,
 *     REQUEST_PATH?: string
 * } $_LUMINOVA
 */
$_LUMINOVA = [
    'NOVAKIT_VERSION'  => getenv('NOVAKIT_VERSION') ?: '3.0.0',
    'LUMINOVA_VERSION' => getenv('LUMINOVA_VERSION') ?: '3.8.5',
];

$_LUMINOVA['SERVER_SOFTWARE'] = sprintf(
    '(NovaKit/%s) (Luminova/%s) (PHP/%s; Development Server)',
    $_LUMINOVA['NOVAKIT_VERSION'],
    $_LUMINOVA['LUMINOVA_VERSION'],
    PHP_VERSION
);

// Identify the Luminova-managed PHP development server.
putenv('LUMINOVA_PHP_CLI_SERVER=' . $_LUMINOVA['NOVAKIT_VERSION']);
putenv('RUNTIME_ENV=novakit');
putenv('SERVER_SOFTWARE=' . $_LUMINOVA['SERVER_SOFTWARE']);

// CLI execution does not require HTTP request handling.
if (in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
    return;
}

// Set Luminova development server request variables.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['RUNTIME_ENV'] = 'novakit';
$_SERVER['LUMINOVA_VERSION'] = $_LUMINOVA['LUMINOVA_VERSION'];
$_SERVER['NOVAKIT_VERSION'] = $_LUMINOVA['NOVAKIT_VERSION'];
$_SERVER['SERVER_SOFTWARE'] = $_LUMINOVA['SERVER_SOFTWARE'];
$_SERVER['LUMINOVA_PHP_CLI_SERVER'] = $_LUMINOVA['NOVAKIT_VERSION'];

function __php_server_find_root(): string 
{
    $paths = [
        getcwd(), // Just in case it's PHPVCI
        dirname(__DIR__, 3)
    ];

    foreach($paths as $path){
        if(!is_string($path)){
            continue;
        }

        $root = "{$path}/public";

        if(is_file("{$root}/index.php")){
            return $root;
        }
    }

    return '';
}

$DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT']
    ?? __php_server_find_root();

$DOCUMENT_ROOT = rtrim(
    realpath($DOCUMENT_ROOT) ?: $DOCUMENT_ROOT,
    '/\\'
);

$REQUEST_URI = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
) ?: '/';

$REQUEST_URI = rawurldecode($REQUEST_URI);

unset($_LUMINOVA);

if ($REQUEST_URI !== '/' && !str_contains($REQUEST_URI, "\0")) {
    $REQUEST_PATH = $DOCUMENT_ROOT
        . DIRECTORY_SEPARATOR
        . ltrim(
            str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                $REQUEST_URI
            ),
            DIRECTORY_SEPARATOR
        );

    $RESOLVED_PATH = realpath($REQUEST_PATH);

    if ($RESOLVED_PATH !== false) {
        $PROJECT_ROOT = dirname($DOCUMENT_ROOT);
        $ROOT_PREFIX = $DOCUMENT_ROOT . DIRECTORY_SEPARATOR;
        $PROJECT_PREFIX = $PROJECT_ROOT . DIRECTORY_SEPARATOR;

        $isInsideRoot = (DIRECTORY_SEPARATOR === '\\')
            ? strncasecmp($RESOLVED_PATH, $ROOT_PREFIX, strlen($ROOT_PREFIX)) === 0
                || strcasecmp($RESOLVED_PATH, $DOCUMENT_ROOT) === 0
            : str_starts_with($RESOLVED_PATH, $ROOT_PREFIX)
                || $RESOLVED_PATH === $DOCUMENT_ROOT;

        // Allow a symbolic link to resolve outside public/,
        // provided its target remains inside the project root.
        $isAllowedLink = is_link($REQUEST_PATH)
            && (
                (DIRECTORY_SEPARATOR === '\\')
                    ? strncasecmp($RESOLVED_PATH, $PROJECT_PREFIX, strlen($PROJECT_PREFIX)) === 0
                        || strcasecmp($RESOLVED_PATH, $PROJECT_ROOT) === 0
                    : str_starts_with($RESOLVED_PATH, $PROJECT_PREFIX)
                        || $RESOLVED_PATH === $PROJECT_ROOT
            );

        if (
            ($isInsideRoot || $isAllowedLink)
            && (file_exists($RESOLVED_PATH) || is_link($REQUEST_PATH))
        ) {
            return false;
        }
    }
}

// Forward the request to Luminova's front controller.
require "{$DOCUMENT_ROOT}/index.php";
exit(0);