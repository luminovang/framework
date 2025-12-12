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
     * Invokable controllers.
     * 
     * @var array<int,class-string<Migration>> $invokes
     */
    private static array $invokes = [];

    /**
     * Run the migrations.
     *
     * @return bool Return true if migration creation was successful, otherwise false.
     * @example - Implementation.
     * 
     * ```php 
     * public function up(): bool 
     * {
     *      return Schema::create('users', function(\Luminova\Database\Table $table){
     *          $table->database = Table::MYSQL;
     * 
     *          $table->id('uid');
     *          $table->string('name')->nullable(false);
     *          $table->timestamps();
     * 
     *          return $table;
     *      });
     * }
     * ```
     */
    public abstract function up(): bool;

    /**
     * Reverse the migrations.
     *
     * @return bool Return true if migration table drop was successful, otherwise false.
     * @example - Implementation:
     * 
     * ```php 
     * public function down(): bool 
     * {
     *      return Schema::drop('users');
     *      // Or if exists
     *      // return Schema::dropIfExists('users');
     * }
     * ```
     */
    public abstract function down(): bool;

    /**
     * Modify table migrations.
     *
     * @return bool Return true if migration modification was successful, otherwise false.
     * 
     * @example - Implementation.
     * 
     * ```php
     * public function alter(): bool 
     * {
     *      return Schema::modify('users', function(\Luminova\Database\Table $table){
     *          $table->string('name')->nullable(true);
     * 
     *          return $table;
     *      });
     * 
     *      // Or rename table.
     *      // return Schema::rename('users', 'user_table');
     * }
     * ```
     */
    public abstract function alter(): bool;

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