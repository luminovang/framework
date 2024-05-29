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

final class Commands 
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
            'description' => "Command helps options for nokakit cli tool.",
            'usages' => [
                'php index.php <command> <argument> ',
                'php index.php <command> <option> <argument> <option>',
                'php index.php <command> <segment> <argument> <option>',
                'novakit <command> <argument>',
                'novakit <command> <option> <argument> <option>',
                'novakit <command> <segment> <argument> <option>'
            ],
            'options' => [
                'foo -help' => 'Show help related to foo command',
                'create:controller userController --extend Controller' => 'Create user controller specifying class and class to extend.',
                'create:controller userController' => 'Create user controller.',
                'create:model myModel' => 'Create a model and extend BaseModel.',
                'create:view user' => 'Create user view',
                'create:view user --dir users' => 'Create user view in users directory.',
                'create:class FooClass' => 'Create class.',
                'create:class FooClass --extend otherClass' => 'Create class specifying class other class to extend.',
                'create:class FooClass --extend otherClass --dir myPath' => 'Create class specifying class other class to extend and directory to class.',
                'myControllerClass segment --name Peter --id 1' => 'Query your controller class, pass method as segment and parameter key followed by value.',
            ]
        ],
        'build:project' => [
            'name' => 'build:project',
            'group' => 'System',
            'description' => "Generates application build files for production",
            'usages' => 'php novakit build:project',
            'options' => [
                '--type zip' => "build",
                '--type build'
            ]
        ],
        'generate:key' => [
            'name' => 'generate:key',
            'group' => 'System',
            'description' => "Generates application key",
            'usages' => 'php novakit generate:key',
            'options' => [

            ]
        ],
        'generate:sitemap' => [
            'name' => 'generate:sitemap',
            'group' => 'System',
            'description' => "Generates application sitemap",
            'usages' => 'php novakit generate:sitemap',
            'options' => [

            ]
        ],
        'env:add' => [
            'name' => 'env:add',
            'group' => 'System',
            'description' => "Add a variable to env file",
            'usages' => 'php novakit env:add',
            'options' => [

            ]
        ],
        'env:remove' => [
            'name' => 'env:remove',
            'group' => 'System',
            'description' => "Remove a variable from env file",
            'usages' => 'php novakit env:remove',
            'options' => [

            ]
        ],
        'context' => [
            'name' => 'context',
            'group' => 'System',
            'description' => "Generates application key",
            'usages' => 'php novakit context "name"',
            'options' => [

            ]
        ],
        'create:controller' => [
            'name' => 'create:controller',
            'group' => 'Generators',
            'description' => "Create a new controller class",
            'usages' => [
                '<command> <argument> <option>',
                '<command> <option>',
                '<command> <argument> <option> <argument> <option>',
            ],
            'options' => [
                'userController -extend Controller' => 'Create user controller specifying class and class to extend.',
                'userController' => 'Create user controller.',
            ]
        ],
        'create:view' => [
            'name' => 'create:view',
            'group' => 'Generators',
            'description' => "Create a new template view",
            'usages' => [
                '<command> <option> ',
                '<command> <option> <argument>'
            ],
            'options' => [
                'user' => 'Create user.php template view.',
                'user -directory user' => 'Create user.php template view in user/ directory.',
            ]
        ],
        'create:class' => [
            'name' => 'create:class',
            'group' => 'Generators',
            'description' => "Create a new controller class",
            'usages' => [
                '<command> <option>',
                '<command> <option> <argument> <option>'
            ],
            'options' => [
                'FooClass' => 'Create a new class.',
                'FooClass -extend BarClass' => 'Create a new class and extend another class.',
                'FooClass -extend BarClass -directory path-name' => 'Create a new class, extend another class and save in specific directory.',
            ]
        ],
        'create:model' => [
            'name' => 'create:model',
            'group' => 'Generators',
            'description' => "Create a new model class",
            'usages' => [
                '<command> <option>',
                '<command> <option> <argument> <option>'
            ],
            'options' => [
                'FooModel' => 'Create a new model class.',
                'FooModel -implement ClassInterface' => 'Create a new model class and implement a class interface.'
            ]
        ],
        'list' => [
            'name' => 'list',
            'group' => 'Lists',
            'description' => "List available commands it descriptions.",
            'usages' => 'php novakit list',
            'options' => []
        ],
        'db:create' => [
            'name' => 'db:create',
            'group' => 'Database',
            'description' => "Create database datable if not exist",
            'usages' => '',
            'options' => [

            ]
        ],
        'db:update' => [
            'name' => 'db:update',
            'group' => 'Database',
            'description' => "Update database record",
            'usages' => '',
            'options' => [

            ]
        ],
        'db:insert' => [
            'name' => 'db:insert',
            'group' => 'Database',
            'description' => "Insert new record to database",
            'usages' => '',
            'options' => [

            ]
        ],
        'db:drop' => [
            'name' => 'db:drop',
            'group' => 'Database',
            'description' => "Drop database table",
            'usages' => '',
            'options' => [

            ]
        ],
        'db:delete' => [
            'name' => 'db:delete',
            'group' => 'Database',
            'description' => "Delete record from database table",
            'usages' => '',
            'options' => [

            ]
        ],
        'db:truncate' => [
            'name' => 'db:truncate',
            'group' => 'Database',
            'description' => "Clear all database table records",
            'usages' => '',
            'options' => [

            ]
        ],  
        'db:select' => [
            'name' => 'db:select',
            'group' => 'Database',
            'description' => "Select record from database",
            'usages' => '',
            'options' => [

            ]
        ],
        'server' => [
            'name' => 'server',
            'group' => 'Server',
            'description' => "Start Luminova PHP development server",
            'usages' => [
                'php novakit server',
                'php novakit server --host localhost --port 8080',
                'php novakit server <flag> <option>',
            ],
            'options' => [
                '--php'  => 'The PHP Binary [default: "PHP_BINARY"]',
                '--host' => 'The HTTP Host [default: "localhost"]',
                '--port' => 'The HTTP Host Port [default: "8080"]',
            ]
        ],
        'cache' => [
            'name' => 'cache',
            'group' => 'Cache',
            'description' => "Manage system caches, clear, delete by key or list cache inhumations",
            'usages' => [
                'php novakit cache:clear',
                'php novakit cache:clear --key <key>',
                'php novakit cache:list',
            ],
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