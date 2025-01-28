<?php 
declare(strict_types = 1);
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$node \\(PhpParser\\\\Node\\\\Stmt\\) of method Luminova\\\\Debugger\\\\PHPStanRules\\:\\:processNode\\(\\) should be contravariant with parameter \\$node \\(PhpParser\\\\Node\\) of method PHPStan\\\\Rules\\\\Rule\\<PhpParser\\\\Node\\>\\:\\:processNode\\(\\)$#',
	'count' => 1,
	'path' => __DIR__ . '/src/Debugger/PHPStanRules.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
