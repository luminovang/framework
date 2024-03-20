<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Command\Novakit;

class Commands 
{
    /**
     * List of available commands
     *
     * @var array<string, mixed> $commands
    */
    protected static $commands = [
        'help' => [
            'name' => 'help',
            'group' => 'Help',
            'usage' => [
                'php index.php <command> <argument> ',
                'php index.php <command> <option> <argument> <option>',
                'php index.php <command> <segment> <argument> <option>',
                'novakit <command> <argument>',
                'novakit <command> <option> <argument> <option>',
                'novakit <command> <segment> <argument> <option>'
            ],
            'description' => "Command helps options for nokakit cli tool.",
            'options' => [
                'foo -help' => 'Show help related to foo command',
                'create:controller userController -extend Controller' => 'Create user controller specifying class and class to extend.',
                'create:controller userController' => 'Create user controller.',
                'create:model userModel -extend userController' => 'Create user model and extend userController.',
                'create:view user' => 'Create user view',
                'create:view user -directory users' => 'Create user view in users directory.',
                'create:class myClass' => 'Create class.',
                'create:class myClass -extend otherClass' => 'Create class specifying class other class to extend.',
                'create:class myClass -extend otherClass -directory myPath' => 'Create class specifying class other class to extend and directory to class.',
                'myControllerClass segment -name Peter -id 1' => 'Query your controller class, pass method as segment and parameter key followed by value.',
            ]
        ],
        'build:project' => [
            'name' => 'build:project',
            'group' => 'System',
            'usage' => 'php novakit build:project',
            'description' => "Generates application build files for production",
            'options' => [
                '--type zip',
                '--type build'
            ]
        ],
        'generate:key' => [
            'name' => 'generate:key',
            'group' => 'System',
            'usage' => 'php novakit generate:key',
            'description' => "Generates application key",
            'options' => [

            ]
        ],
        'create:controller' => [
            'name' => 'create:controller',
            'group' => 'Generators',
            'usage' => [
                '<command> <argument> <option>',
                '<command> <option>',
                '<command> <argument> <option> <argument> <option>',
            ],
            'description' => "Create a new controller class",
            'options' => [
                'userController -extend Controller' => 'Create user controller specifying class and class to extend.',
                'userController' => 'Create user controller.',
            ]
        ],
        'create:view' => [
            'name' => 'create:view',
            'group' => 'Generators',
            'usage' => [
                '<command> <option> ',
                '<command> <option> <argument>'
            ],
            'description' => "Create a new template view",
            'options' => [
                'user' => 'Create user.php template view.',
                'user -directory user' => 'Create user.php template view in user/ directory.',
            ]
        ],
        'create:class' => [
            'name' => 'create:class',
            'group' => 'Generators',
            'usage' => [
                '<command> <option>',
                '<command> <option> <argument> <option>'
            ],
            'description' => "Create a new controller class",
            'options' => [
                'myClass' => 'Create a new class.',
                'myClass -extend otherClass' => 'Create a new class and extend otherClass.',
                'myClass -extend otherClass -directory myPath' => 'Create a new class and extend otherClass, save in myPath directory.',
            ]
        ],
        'list' => [
            'name' => 'list',
            'group' => 'Lists',
            'usage' => 'php novakit list',
            'description' => "List available commands it descriptions.",
            'options' => []
        ],
        'db:create' => [
            'name' => 'db:create',
            'group' => 'Database',
            'usage' => '',
            'description' => "Create database datable if not exist",
            'options' => [

            ]
        ],
        'db:update' => [
            'name' => 'db:update',
            'group' => 'Database',
            'usage' => '',
            'description' => "Update database record",
            'options' => [

            ]
        ],
        'db:insert' => [
            'name' => 'db:insert',
            'group' => 'Database',
            'usage' => '',
            'description' => "Insert new record to database",
            'options' => [

            ]
        ],
        'db:drop' => [
            'name' => 'db:drop',
            'group' => 'Database',
            'usage' => '',
            'description' => "Drop database table",
            'options' => [

            ]
        ],
        'db:delete' => [
            'name' => 'db:delete',
            'group' => 'Database',
            'usage' => '',
            'description' => "Delete record from database table",
            'options' => [

            ]
        ],
        'db:truncate' => [
            'name' => 'db:truncate',
            'group' => 'Database',
            'usage' => '',
            'description' => "Clear all database table records",
            'options' => [

            ]
        ],  
        'db:select' => [
            'name' => 'db:select',
            'group' => 'Database',
            'usage' => '',
            'description' => "Select record from database",
            'options' => [

            ]
        ],
        'server' => [
            'name' => 'server',
            'group' => 'Server',
            'usage' => [
                'php novakit server',
                'php novakit server --host localhost --port 8080',
                'php novakit server <flag> <option>',
            ],
            'description' => "Start Luminova PHP development server",
            'options' => [
                '--php'  => 'The PHP Binary [default: "PHP_BINARY"]',
                '--host' => 'The HTTP Host [default: "localhost"]',
                '--port' => 'The HTTP Host Port [default: "8080"]',
            ]
        ],
        'cache' => [
            'name' => 'cache',
            'group' => 'Cache',
            'usage' => [
                'php novakit cache:clear',
                'php novakit cache:clear --key <key>',
                'php novakit cache:list',
            ],
            'description' => "Manage system caches, clear, delete by key or list cache inhumations",
            'options' => [
                '--key'  => 'Set the cache key to delete',
                '--storage'  => 'Set the cache storage name to delete',
            ]
        ],
    ];

    /**
     * Get all available commands
     * 
     * @return array
    */
    public static function getCommands(): array 
    {
        asort(self::$commands);
        
        return self::$commands;
    }

    /**
     * Get command information
     * 
     * @param string $key command name 
     * 
     * @return array
    */
    public static function get(string $key): array 
    {
        return self::$commands[$key] ?? [];
    }

    /**
     * Check if command exists
     * 
     * @param string $key command name 
     * 
     * @return bool
    */
    public static function has(string $key): bool
    {
        return self::get($key) !== [];
    }
}