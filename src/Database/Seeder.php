<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Database;

use \Luminova\Database\Builder;

abstract class Seeder
{
    /**
     * @var array<int,class-string<Seeder>> $invokes
    */
    private static array $invokes = [];
    /**
     * Run database table seeder.
     * 
     * @param Builder $builder Database builder instance.
     * 
     * @return void
     */
    public abstract function run(Builder $builder): void;

    /**
     * Invoke another seeder within seeder run method.
     * 
     * @param class-string<Seeder> $seed The seeder class name.
    */
    protected final function invoke(string $seed): void
    {
        static::$invokes[] = $seed;
    }

    /**
     * Get database seeder invokes.
     * 
     * @return array<int,class-string<Seeder>> Return seeders.
    */
    public final function getInvokes(): array
    {
        return static::$invokes;
    }
}