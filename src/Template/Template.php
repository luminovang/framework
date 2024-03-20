<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Template;

use Luminova\Template\TemplateTrait; 

class Template 
{ 
    use TemplateTrait;

    public function __construct(string $dir = __DIR__) {

        // Initialize the template engine
        $this->initialize($dir);

        // Set the project base path
        //$this->setBasePath($this->getBasePath());
    }
}