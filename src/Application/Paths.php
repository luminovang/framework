<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Application;

class Paths 
{
    /**
     * @var string $system
    */
    protected string $system =  'system/';
    
    /**
     * @var string $systemPlugins
    */
    protected string $plugins = 'system/plugins/';

    /**
     * @var string $systemPlugins
    */
    protected string $library = 'libraries/libs/';

    /**
     * @var string $controllers
    */
    protected string $controllers =  'app/Controllers/';

    /**
     * @var string $writeable
     */
    protected string $writeable =  'writeable/';

    /**
     * @var string $logs
     */
    protected string $logs =  'writeable/log/';

    /**
     * @var string $caches
     */
    protected string $caches =  'writeable/caches/';

    /**
     * @var string $public 
     */
    protected string $public = 'public/';

     /**
     * @var string $assets
     */
    protected string $assets = 'public/assets/';

    /**
     * @var string $views
     */
    protected string $views =  'resources/views/';

     /**
     * @var string $routes
     */
    protected string $routes =  'routes/';

     /**
     * @var string $languages
     */
    protected string $languages =  'app/Controllers/Languages/';

    /**
     * Get path 
     * 
     * @param string $key
     * 
     * @return string $path 
    */
    public function __get(string $key): string 
    {
        $path = $this->{$key} ?? '';

        if($path  === ''){
            return '';
        }
        
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

        return root(__DIR__, $path);
    }

    /**
     * Create directory if not exists
     * 
     * @param string $path
     * @param int $permissions 
     * 
     * @return bool true if files existed or was created else false
    */
    public static function createDirectory(string $path, int $permissions = 0755): bool 
    {
        if (!file_exists($path)) {
            return mkdir($path, $permissions, true);
        }

        return true;
    }
}