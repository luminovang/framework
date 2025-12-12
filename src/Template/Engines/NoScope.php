<?php 
declare(strict_types=1);
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Template\Engines;

use Luminova\Debugger\Tracer;
use Luminova\Exceptions\RuntimeException;

/**
 * Creates and returns the guard object used when template isolation is disabled.
 *
 * The exception includes the file and line where `$self` was accessed, making it
 * easier to spot improper usage during development.
 * 
 * @throws RuntimeException When `$self` is accessed in non-isolation mode.
 */
final class NoScope 
{
    /**
     * Guard instance that traps all `$self` interactions.
     *
     * @param integer $id
     */
    public function __construct(private int $id = 0) {$this->id = spl_object_id($this);}
    public function __id():int { return $this->id; }
    public function __is(int $id):bool { return $this->id === $id; }
    public function __get(string $p) { $this->e(); }
    public function __call(string $m, array $args) { $this->e(); }
    public function __set(string $p, mixed $v){ $this->e(); }
    public function __toString() { $this->e(); }

    private function e(): void
    {
        [$file, $line] = Tracer::trace(2);
        $e = new RuntimeException(
            'Using "$self" is not available in non-isolation mode. ' .
            'Enable "templateIsolation" in template configuration to use "$self" keyword.'
        );

        if($file){
            $e->setLine($line)->setFile($file);
        }

        throw $e;
    }
}