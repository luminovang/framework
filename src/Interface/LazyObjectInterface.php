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
 * Marker interface for lazily initialized objects.
 * 
 * Implementing this interface allows classes to support lazy initialization 
 * while ensuring compatibility with type casting for original object types.
 *
 * Classes implementing this interface indicate they can be treated as lazy 
 * objects, helping IDEs recognize and suggest the original class methods 
 * when type-hinted alongside `LazyObjectInterface`.
 */
interface LazyObjectInterface
{
    // This is a marker interface with no methods.
}