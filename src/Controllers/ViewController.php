<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

 namespace Luminova\Controllers;

 use \App\Controllers\Application;
 use \Luminova\Http\Request;
 use \Luminova\Security\InputValidator;
 use \Luminova\Library\Importer;
 
 abstract class ViewController
 {
     /**
      * HTTP request object 
      * @var Request $request 
     */
     private static ?Request $_request = null;
 
     /**
      * Input validation object 
      * @var InputValidator $validate
     */
    private static ?InputValidator $_validate = null;
 
     /**
      * Application instance
      * @var Application $app 
     */
    private static ?Application $_app = null;
 
     /**
      * Importer instance
      * @var Importer $library 
     */
    private static ?Importer $_library = null;

    /**
    * Initializes the http request class
    * Allows #[\Override]
    * 
    * @return Request $request http request object 
    */
    protected function request(): Request
    {
        if(self::$_request === null){
            self::$_request = new Request();
        }

        return self::$_request;
    }

    /**
     * Initializes the input validator class
    * Allows #[\Override]
    * 
    * @return InputValidator $validate input validation object 
    */
    protected function validate(): InputValidator
    {
        if(self::$_validate === null){
            self::$_validate = new InputValidator();
        }
        
        return self::$_validate;
    }

    /**
     * Initializes the application class
    * Allows #[\Override]
    * 
    * @return Application $app Application instance
    */
    protected function app(): Application
    {
        if(self::$_app === null){
            self::$_app = new Application();
        }
        
        return self::$_app;
    }

    /**
     * Initializes the application class
    * Allows #[\Override]
    * 
    * @return Importer $app Application instance
    */
    protected function library(): Importer
    {
        if(self::$_library === null){
            self::$_library = new Importer();
        }
        
        return self::$_library;
    }

    /**
     * Render view
     *
     * @param string $view view name
     * @param array $options view options
     * 
     * @return int STATUS_OK
    */
    protected function view(string $view, array $options = []): int
    {
        $this->app()->view($view)->render($options);

        return STATUS_SUCCESS;
    }

    /**
     * On create method 
     * 
     * @return void 
    */
    protected function onCreate(): void {}

    /**
     * On destroy method 
     * 
     * @return void 
    */
    protected function onDestroy(): void {}
   // abstract protected function onDestroy(): void;
 }