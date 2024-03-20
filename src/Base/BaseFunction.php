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
    /**
     * @var string RANDOM_INT
    */
    public const RANDOM_INT = "int";

    /**
     * @var string RANDOM_CHAR
    */
	public const RANDOM_CHAR = "char";

    /**
     * @var string RANDOM_STR
    */
	public const RANDOM_STR = "str";

    /**
     * @var string RANDOM_SALT
    */
	public const RANDOM_SALT = "salt";

    /**
     * @var string RANDOM_SID
    */
	public const RANDOM_SID = "sid";

    /**
     * @var string RANDOM_UUID
    */
	public const RANDOM_UUID = "uui";

    /**
     * @var string RANDOM_PASS
    */
	public const RANDOM_PASS = "pass";
    
    /**
     * @method FunctionTrait
     */
    use FunctionTrait;

    /**
     * @method StringTrait
     */
    use StringTrait;

}