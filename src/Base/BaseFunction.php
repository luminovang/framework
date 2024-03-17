<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Base;

use \Luminova\Functions\FunctionTrait;
use \Luminova\Functions\StringTrait;

abstract class BaseFunction
{
    public const INT = "int";
	public const CHAR = "char";
	public const STR = "str";
	public const SALT = "salt";
	public const SID = "sid";
	public const UUI = "uui";
	public const PASS = "pass";
    
    /**
     * @class FunctionTrait
     */
    use FunctionTrait;

    /**
     * @class StringTrait
     */
    use StringTrait;

}