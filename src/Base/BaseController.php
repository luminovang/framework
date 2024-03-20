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

use \Luminova\Base\BaseViewController;
use \Luminova\Http\Request;
use \Luminova\Security\InputValidator;
use \Luminova\Library\Importer;
use \App\Controllers\Application;

abstract class BaseController extends BaseViewController
{
    /**
      * HTTP request object 
      * @var Request $request 
    */
    protected ?Request $request = null;
 
    /**
      * Input validation object 
      * @var InputValidator $validate
    */
    protected ?InputValidator $validate = null;
 
    /**
      * Application instance
      * @var Application $app 
    */
    protected ?Application $app = null;
 
    /**
      * Importer instance
      * @var Importer $library 
    */
    protected ?Importer $library = null;
 
    /**
     * Initialize controller instance
     * Make request and validate available global
    */
    public function __construct()
    {
        parent::__construct();
        $this->validate = $this->validate();
        $this->request = $this->request();
    }

    /**
     * Property getter
     *
     * @param string $key property key
     * 
     * @return ?mixed return property else null
    */
    public function __get(string $key): mixed
    {
        return $this->{$key} ?? null;
    }
    
     /**
     * Check if property is set
     *
     * @param string $key property key
     * 
     * @return bool 
    */
    public function __isset(string $key): bool
    {
        return isset($this->{$key});
    }
}