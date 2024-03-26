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
     * Flag for gnerating random numbers.
     * 
     * @var string RANDOM_INT
    */
    public const RANDOM_INT = "int";

    /**
     * Flag for gnerating random characters.
     * 
     * @var string RANDOM_CHAR
    */
	public const RANDOM_CHAR = "char";

    /**
     * Flag for gnerating random string.
     * 
     * @var string RANDOM_STR
    */
	public const RANDOM_STR = "str";

    /**
     * Flag for gnerating random salt.
     * 
     * @var string RANDOM_SALT
    */
	public const RANDOM_SALT = "salt";

    /**
     * Flag for gnerating random ssid.
     * 
     * @var string RANDOM_SID
    */
	public const RANDOM_SID = "sid";

    /**
     * Flag for gnerating random uuid.
     * 
     * @var string RANDOM_UUID
    */
	public const RANDOM_UUID = "uui";

    /**
     * Flag for gnerating random password.
     * 
     * @var string RANDOM_PASS
    */
	public const RANDOM_PASS = "pass";

    use FunctionTrait;
    use StringTrait;

}