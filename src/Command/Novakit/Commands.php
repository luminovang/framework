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
use \Luminova\Application\Foundation;

final class Commands 
{
    /**
     * List of available commands
     *
     * @var array<string, mixed> $commands
    */
    protected static array $commands = [
        'help' => [
            'name' => 'help',
            'group' => 'Help',
            'description' => "\033[1;33mPHP Luminova Novakit Command Help (Novakit Version: " . Foundation::NOVAKIT_VERSION . ", Framework Version: " . Foundation::VERSION . ")\n\033[0mThis command displays help options for the Novakit CLI tool.\n\nTo execute `novakit` commands, run them from your application's root directory (e.g., 'php novakit command'). For controller-related commands, navigate to the `public` directory before execution (e.g., 'php index.php command').\n\n\033[1;31mIMPORTANT NOTE:\033[0m\nThe \033[1;33m--help\033[0m (\033[1;33m-h\033[0m) options are reserved for displaying help messages and should not be used for custom arguments when creating CLI applications.",
            'usages' => [
                'php novakit <command> --help',
                'php index.php <controller-command> --help',
                'php novakit <NovaKitCommand> --help',
                'php index.php <ControllerCommandGroup> --help',
                'php novakit <NovaKitCommand> --foo=bar baz',
                'php index.php <ControllerCommandGroup> --foo=bar --baz',
            ],
            'options' => [
                '-h, --help' => "Display help message related to novakit or controller command.",
                '-a, --all' => "Display all available novakit help messages.",
                '--no-header' => "Disable displaying novakit header information.",
                '-v, --version' => "Display Framework and Novakit Command Line version information.",
            ],
            'examples' => [
                'php novakit list --help' => 'Display help for listing all available commands and their descriptions.',
                'php novakit server --help' => 'Displays help for starting the Luminova PHP development server.',
                'php novakit context --help' => "Displays help for installing the application router context.",
                'php novakit cache --help' => "Displays help for managing system caches: clear, delete by key, or list cache items.",
                'php novakit build:project --help' => 'Displays help for building the Luminova PHP project.',
                'php novakit generate:key --help' => "Displays help for generating an application encryption key and storing it in environment variables.",
                'php novakit generate:sitemap --help' => "Displays help for generating the website sitemap.",
                'php novakit env:add --help' => "Displays help for adding or updating an environment variable.",
                'php novakit env:remove --help' => "Displays help for removing a variable from the .env file.",
                'php novakit create:controller --help' => "Displays help for creating a new controller class.",
                'php novakit create:view --help' => "Displays help for creating a new template view.",
                'php novakit create:class --help' => "Displays help for creating a new class file.",
                'php novakit create:model --help' => "Displays help for creating a new model class file.",
                'php novakit db:drop --help' => "Displays help for dropping the database migration table.",
                'php novakit db:alter --help' => "Displays help for altering database migration tables and columns.",
                'php novakit db:truncate --help' => "Displays help for truncating a database table to clear all records.",
                'php novakit db:seed --help' => "Displays help for executing database seeders.",
                'php novakit db:migrate --help' => 'Displays help for executing database table migrations.',
                'php novakit cron:create --help' => "Displays help for creating cron tasks and locking them in the cron lock file.",
                'php novakit cron:run --help' => "Displays help for running cron jobs that are locked in the cron lock file.",
                'php index.php YourControllerCommandGroup --help' => 'Display help information related to your controller commands.',
            ],
        ],
        'list' => [
            'name' => 'list',
            'group' => 'Lists',
            'description' => "Lists all available commands and their descriptions.",
            'usages' => [
                'php novakit list',
            ],
            'options' => [],
            'examples' => [],
        ],
        'server' => [
            'name' => 'server',
            'group' => 'Server',
            'description' => "Starts Luminova PHP development server.",
            'usages' => [
                'php novakit server',
            ],
            'options' => [
                '-b, --php'  => 'Specify the PHP Binary location to use.',
                '-h, --host' => 'Specify the HTTP Hostname to use.',
                '-p, --port' => 'Specify the HTTP Host port to use.',
            ],
            'examples' => [
                'php novakit server',
                'php novakit server --host=localhost --port=8080 --php=PHP-BINARY-PATH',
            ],
        ],
        'build:project' => [
            'name' => 'build:project',
            'group' => 'System',
            'description' => "Generates application build files for production.",
            'usages' => [
                'php novakit build:project',
            ],
            'options' => [
                '--type' => "Specify the type of build to generate (`zip` or `build`).",
            ],
            'examples' => [
                'php novakit build:project --type=zip',
                'php novakit build:project --type=build',
            ],
        ],
        'generate:key' => [
            'name' => 'generate:key',
            'group' => 'System',
            'description' => "Generates an application encryption key and stores it in environment variables.",
            'usages' => [
                'php novakit generate:key'
            ],
            'options' => [
                '--no-save' => 'Do not save the generated application key to the .env file.',
            ],
            'examples' => [
                'php novakit generate:key',
                'php novakit generate:key --no-save',
            ],
        ],
        'generate:sitemap' => [
            'name' => 'generate:sitemap',
            'group' => 'System',
            'description' => "Generates the application website sitemap.",
            'usages' => [
                'php novakit generate:sitemap',
            ],
            'options' => [],
            'examples' => [],
        ],
        'env:add' => [
            'name' => 'env:add',
            'group' => 'System',
            'description' => "Adds or updates an environment variable with a new key and value.",
            'usages' => [
                'php novakit env:add',
            ],
            'options' => [
                '--key' => 'Specify the environment key name to add.',
                '--value' => 'Specify the environment key content value to add.',
            ],
            'examples' => [
                'php novakit env:add --key="test.key" --value="test key value"',
            ],
        ],
        'env:remove' => [
            'name' => 'env:remove',
            'group' => 'System',
            'description' => "Removes a variable from the .env file.",
            'usages' => [
                'php novakit env:remove --key="test.key"',
            ],
            'options' => [
                '--key' => 'Specify the environment key name to remove.',
            ],
            'examples' => [
                'php novakit env:remove --key="test.key"',
            ],
        ],
        'context' => [
            'name' => 'context',
            'group' => 'System',
            'description' => "Install application route context or create routes from defined route annotation attributes.",
            'usages' => [
                'php novakit context <context-name>',
                'php novakit context --export-attr',
            ], 
            'options' => [
                '-e, --export-attr' => 'Indicate to export and build routes from route annotations.',
                '-n, --no-error' => 'Indicate to leave error callback handler `NULL` while adding new context.',
            ],
            'examples' => [
                'php novakit context "foo"',
                'php novakit context "foo" --no-error',
                'php novakit context --export-attr',
            ],
        ],
        'create:controller' => [
            'name' => 'create:controller',
            'group' => 'Generators',
            'description' => "Creates a new controller class and optionally extends another class.",
            'usages' => [
                'php novakit create:controller TestController'
            ],
            'options' => [
                '--extend' => 'Specify the controller class name to extend.',
                '--dir' => 'Specify the directory within the `/app/Controllers/` directory to store the controller file.',
                '--type' => 'Specify the type of controller class you wish to create (e.g. `view`, `command` or `request`).',
            ],
            'examples' => [
                'php novakit create:controller TestController',
                'php novakit create:controller TestController --extend=TestBaseController',
                'php novakit create:controller TestController --extend=TestBaseController --type=request',
                'php novakit create:controller TestController --extend=TestBaseController --dir=Test',
            ],
        ],
        'create:view' => [
            'name' => 'create:view',
            'group' => 'Generators',
            'description' => "Creates a new template view.",
            'usages' => [
                'php novakit create:view TestView',
            ],
            'options' => [
                '--dir' => 'Specify the directory within the `resources/views/` directory to store the view file.',
            ],
            'examples' => [
                'php novakit create:view TestView',
                'php novakit create:view TestView --dir=test',
            ],
        ],
        'create:class' => [
            'name' => 'create:class',
            'group' => 'Generators',
            'description' => "Creates a new class file and stores it in the `/app/Controllers/Utils/` directory.",
            'usages' => [
                'php novakit create:class TestClass',
            ],
            'options' => [
                '--implement' => 'Specify the interface class name to implement.',
                '--extend' => 'Specify the class name to extend.',
            ],
            'examples' => [
                'php novakit create:class TestClass',
                'php novakit create:class TestClass --implement=TestInterface',
                'php novakit create:class TestClass --dir=test',
            ],
        ],
        'create:model' => [
            'name' => 'create:model',
            'group' => 'Generators',
            'description' => "Creates a new model class that extends BaseModel and stores it in the `/app/Controllers/Models/` directory.",
            'usages' => [
                'php novakit create:model TestModel'
            ],
            'options' => [
                '--implement' => 'Specify the interface class name to implement.',
            ],
            'examples' => [
                'php novakit create:model TestModel',
                'php novakit create:model TestModel --implement=TestModelInterface',
            ],
        ],
        'db:drop' => [
            'name' => 'db:drop',
            'group' => 'Database',
            'description' => "Drops the database migration table.",
            'usages' => [
                'php novakit db:drop',
                'php novakit db:drop --class=TestMigration',
            ],
            'options' => [
                '-c, --class' => "Specify the migration class to drop.",
                '-n, --no-backup' => "Run migration drop without backup.",
            ],
            'examples' => [
                'php novakit db:drop --class=TestMigration',
                'php novakit db:drop --class=TestMigration --no-backup',
                'php novakit db:drop --no-backup',
            ],
        ],
        'db:clear' => [
            'name' => 'db:clear',
            'group' => 'Database',
            'description' => "Clears lock files for seeders or migrations.",
            'usages' => [
                'php novakit db:clear --lock=seeder',
                'php novakit db:clear --lock=migration',
                'php novakit db:clear --lock=migration --class=TestMigration',
            ],
            'options' => [
                '-l, --lock' => "Specify the context of lock files to clear. Allowed values: 'seeder', 'migration'.",
                '-c, --class' => "Specify the migration or seeder class to clear lock files for.",
            ],
            'examples' => [
                'php novakit db:clear --lock=seeder',
                'php novakit db:clear --lock=migration',
                'php novakit db:clear --lock=migration --class=TestMigration',
            ],
        ],
        'db:alter' => [
            'name' => 'db:alter',
            'group' => 'Database',
            'description' => "Alter database migration tables and columns.",
            'usages' => [
                'php novakit db:alter',
                'php novakit db:alter --class=TestMigration',
            ],
            'options' => [
                '-c, --class' => "Specify the migration class to alter.",
                '-n, --no-backup' => "Run alter migration without creating a backup.",
                '-d, --drop-columns' => "Drop columns that don't exist in the new schema during the alter migration.",
                '-b, --debug' => "Print generated alteration SQL query string without applying any changes."
            ],
            'examples' => [
                'php novakit db:alter --class=TestMigration',
                'php novakit db:alter --class=TestMigration --no-backup',
                'php novakit db:alter --no-backup',
                'php novakit db:alter --class=TestMigration --drop-columns',
                'php novakit db:alter --drop-columns',
            ],
        ],
        'db:truncate' => [
            'name' => 'db:truncate',
            'group' => 'Database',
            'description' => "Truncates a database table to clear all records.",
            'usages' => [
                'php novakit db:truncate --table=TestTable',
            ],
            'options' => [
                '-t, --table' => "Specify the database table name to truncate.",
                '-n, --no-transaction' => "Run database truncation without transaction.",
            ],
            'examples' => [
                'php novakit db:truncate --table=TestTable',
                'php novakit db:truncate --table=TestTable --no-transaction',
            ],
        ],
        'db:seed' => [
            'name' => 'db:seed',
            'group' => 'Database',
            'description' => "Executes database seeders.",
            'usages' => [
                'php novakit db:seed',
                'php novakit db:seed --class=TestSeeder',
            ],
            'options' => [
                '-c, --class' => "Specify the seeder class to run.",
                '-t, --table' => "Specify the seeder class table name to truncate before rolling back.",
                '-r, --rollback' => "Rollback seeder to previous version.",
                '-n, --no-backup' => "Run seeder without backup.",
                '-i, --invoke' => "Run seeder and also invoke other invokable seeders classes.",
            ],
            'examples' => [
                'php novakit db:seed',
                'php novakit db:seed --class=TestSeeder',
                'php novakit db:seed --class=TestSeeder --rollback',
                'php novakit db:seed --class=TestSeeder --rollback --table=Foo',
                'php novakit db:seed --class=TestSeeder --no-backup',
                'php novakit db:seed --no-backup',
            ],
        ],
        'db:migrate' => [
            'name' => 'db:migrate',
            'group' => 'Database',
            'description' => 'Executes database table migrations.',
            'usages' => [
                'php novakit db:migrate',
                'php novakit db:migrate --class=TestMigration'
            ],
            'options' => [
                '-c, --class' => "Specify the migration class to run.",
                '-n, --no-backup' => "Run migration without backup.",
                '-d, --drop' => "Drop table for `down` method during migration.",
                '-r, --rollback' => "Rollback migration to previous version.",
                '-b, --debug' => "Print generated migration SQL query string without applying any changes.",
                '-i, --invoke' => "Run migration and also invoke other invokable migration classes.",
            ],
            'examples' => [
                'php novakit db:migrate',
                'php novakit db:migrate --class=TestMigration',
                'php novakit db:migrate --class=TestMigration --rollback',
                'php novakit db:migrate --class=TestMigration --no-backup',
                'php novakit db:migrate --no-backup'
            ]
        ],
        'cron:create' => [
            'name' => 'cron:create',
            'group' => 'Cron',
            'description' => "Creates cron tasks and locks them in the cron lock file.",
            'usages' => [
                'php novakit cron:create'
            ],
            'options' => [
                '--force'  => 'Force update tasks with new changes from cron class if already locked.',
            ],
            'examples' => [
                'php novakit cron:create',
                'php novakit cron:create --force',
            ],
        ],
        'cron:run' => [
            'name' => 'cron:run',
            'group' => 'Cron',
            'description' => "Runs cron jobs that were locked in the cron lock file.",
            'usages' => [
                'php novakit cron:run'
            ],
            'options' => [
                '--force'  => 'Force update tasks with new changes from cron class if already locked.',
            ],
            'examples' => [
                'php novakit cron:run',
                'php novakit cron:run --force',
            ],
        ],
        'cache' => [
            'name' => 'cache',
            'group' => 'Cache',
            'description' => "Manages system caches: clears, deletes by key, or lists cache items.",
            'usages' => [
                'php novakit cache:clear',
                'php novakit cache:clear --key=TestKey',
                'php novakit cache:list',
            ],
            'options' => [
                '--key'  => 'Specify the cache key to delete.',
                '--storage'  => 'Specify the cache storage name to delete.',
            ],
            'examples' => [
                'php novakit cache:clear',
                'php novakit cache:clear --key=TestKey',
                'php novakit cache:list',
            ],
        ]
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