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
namespace Luminova\Interface;

/**
 * Marker interface indicating that a class supports prototypal behavior.
 *
 * Classes implementing this interface can dynamically extend themselves
 * with new properties and methods at runtime, emulating JavaScript-style
 * prototypal inheritance in PHP.
 *
 * @example - Usages:
 * ```php
 * use Luminova\Components\Object\Prototypeable;
 * use Luminova\Interface\PrototypeableInterface;
 *
 * class User implements PrototypeableInterface
 * {
 *     use Prototypeable;
 * }
 *
 * $user = new User();
 * $user->prototype('nickname', 'Johnny');
 * $user->prototype('sayHello', fn() => "Hello from {$this->nickname}");
 *
 * echo $user->nickname;   // "Johnny"
 * echo $user->sayHello(); // "Hello from Johnny"
 * ```
 */
interface PrototypeableInterface
{}