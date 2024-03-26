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
      * {@inheritdoc}
    */
    protected ?Request $request = null;
 
    /**
      * {@inheritdoc}
    */
    protected ?InputValidator $validate = null;
 
    /**
      * {@inheritdoc}
    */
    protected ?Application $app = null;
 
    /**
      * {@inheritdoc}
    */
    protected ?Importer $library = null;
 
    /**
     * {@inheritdoc}
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
     * @ignore
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
     * @ignore
    */
    public function __isset(string $key): bool
    {
      return isset($this->{$key});
    }
}