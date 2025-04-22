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
namespace Luminova\Database;

abstract class Migration 
{
    /**
     * @var array<int,class-string<Migration>> $invokes
     */
    private static array $invokes = [];

    /**
     * Run the migrations.
     *
     * @return void
     * @example - Implementation.
     * 
     * ```php 
     * Schema::create('foo', function(\Luminova\Database\Table $table){
     *      $table->string('bar');
     *      return $table;
     * });
     * ```
     */
    public abstract function up(): void;

    /**
     * Reverse the migrations.
     *
     * @return void
     * @example - Implementation:
     * 
     * ```php 
     * Schema::drop('foo');
     * ```
     * 
     * @example - Implementation.
     * 
     * ```php 
     * Schema::dropIfExists('foo');
     * ```
     */
    public abstract function down(): void;

    /**
     * Modify table migrations.
     *
     * @return void
     * 
     * @example - Implementation.
     * 
     * ```php
     * Schema::modify('foo', function(\Luminova\Database\Table $table){
     *      $table->string('bar');
     *      return $table;
     * });
     * ```
     * 
     * @example - Implementation.
     * 
     * ```php 
     * Schema::rename('foo', 'bar');
     * ```
     */
    public abstract function alter(): void;

    /**
     * Invoke another migration within migration up or down method.
     * 
     * @param class-string<Migration> $migrate The migration class name.
     * 
     * @return void
     * 
     * @example - Implementation:
     * ```php 
     * Schema::invoke('\App\Database\Migration\AnotherMigration')
     * ```
     */
    protected final function invoke(string $migrate): void
    {
        self::$invokes[] = $migrate;
    }

    /**
     * Get table all invoke migration classes.
     * 
     * @return array<int,class-string<Migration>> Return migration to invoke.
     * @internal
     */
    public final function getInvokes(): array
    {
        return self::$invokes;
    }
}