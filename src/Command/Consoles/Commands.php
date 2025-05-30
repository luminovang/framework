<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Command\Consoles;

use \Luminova\Luminova;
use \Luminova\Command\Utils\Color;
use \Luminova\Command\Utils\Text;

final class Commands 
{
    /**
     * List of available commands.
     *
     * @var array<string,array<string,mixed>> $commands
     */
    private static array $commands = [
        'help' => [
            'name' => 'Help',
            'group' => 'help',
            'description' => 'his command displays help options for the Novakit CLI tool.',
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
                '--system-info' => "Display basic system information.",
                '-a, --all' => "Display all available novakit help messages.",
                '--no-header' => "Disable displaying novakit header information.",
                '--no-color' => "Disable displaying colored text.",
                '-v, --version' => "Display Framework and Novakit Command Line version information."
            ],
            'examples' => [
                'php novakit list --help' => 'Display help for listing all available commands and their descriptions.',
                'php novakit server --help' => 'Displays help for starting the Luminova PHP development server.',
                'php novakit context --help' => "Displays help for installing the application router context.",
                'php novakit cache --help' => "Displays help for managing system caches: clear, delete by key, or list cache items.",
                'php novakit build:project --help' => 'Displays help for building the Luminova PHP project.',
                'php novakit generate:key --help' => "Displays help for generating an application encryption key and storing it in environment variables.",
                'php novakit auth --help' => "Displays help for CLI user authentication.",
                'php novakit generate:sitemap --help' => "Displays help for generating the website sitemap.",
                'php novakit env:add --help' => "Displays help for adding or updating an environment variable.",
                'php novakit env:setup --help' => "Displays help for configuring environment variable based on context.",
                'php novakit env:cache --help' => "Displays help for generating cache version of environment variable for production.",
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
                'php index.php YourControllerCommandGroup --help' => 'Display help information related to routable CLI controller commands.',
            ],
        ],

        'list' => [
            'name' => 'Lists',
            'group' => 'list',
            'description' => "Lists all available novakit commands and their descriptions.",
            'usages' => [
                'php novakit list',
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [],
        ],

       'auth' => [
            'name' => 'Authentication',
            'group' => 'auth',
            'description' => 'CLI helper for user authentication. Authenticate using a username and either a key or password.',
            'usages' => [
                'php novakit auth login --user',
                'php novakit auth logout',
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-u, --user' => 'Username for authentication using a private/public key or password.',
                '-s, --silent-login' => 'Suppress the "login successful" message after authentication.',
            ],
            'examples' => [
                'php novakit auth login -u="username"' => 'Initiate user login with the specified username.',
            ],
        ],

        'log' => [
            'name' => 'Logs',
            'group' => 'log',
            'description' => 'Manage and interact with application log files.',
            'usages' => [
                'php novakit log',
            ],
            'options' => [
                '-l, --level' => 'Specify the log level (e.g., notice, debug) to read or clear.',
                '-s, --start' => 'Specify the starting line offset for reading logs.',
                '-e, --end' => 'Specify the maximum number of lines to read from the log.',
                '-c, --clear' => 'Clear the contents of the specified log level.',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit log --level=notice --start=20 --end=50' => 'Show logs starting from 20 limit 50.',
                'php novakit log --level=notice --end=10' => 'Show most 10 recent logs.',
                'php novakit log --level=debug --clear' => 'Clear the entire logs in specified level.',
            ],
        ],

        'server' => [
            'name' => 'Server',
            'group' => 'server',
            'description' => 'Starts the Luminova PHP development server.',
            'usages' => [
                'php novakit server',
            ],
            'options' => [
                '-b, --php'   => 'Specify the PHP binary location.',
                '-h, --host'  => 'Specify the development hostname.',
                '-p, --port'  => 'Specify the port for the development server.',
                '-t, --testing' => 'Start the server with the network address for testing on other devices.',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit server' => 'Start the development server on localhost.',
                'php novakit server --port=8080 --testing' => 'Start the server on port 8080 for testing on other devices.',
                'php novakit server --host=localhost --port=8080 --php=<PHP-BINARY-PATH>' => 'Start the server using a specified PHP binary.',
            ],
        ],

        'build:project' => [
            'name' => 'Builder',
            'group' => 'build:project',
            'description' => "Archive required application files for production based on 'app.version' or copy them to build directory without zipping.",
            'usages' => [
                'php novakit build:project',
            ],
            'options' => [
                '--type' => "Specify the type of build to generate (`zip` or `build`).",
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit build:project --type=zip' => 'Generates application production files as zip files',
                'php novakit build:project --type=build' => 'Copy application production files to build directory.',
            ],
        ],

        'generate:key' => [
            'name' => 'System',
            'group' => 'generate:key',
            'description' => "Generates an application encryption key and stores it in environment variables.",
            'usages' => [
                'php novakit generate:key'
            ],
            'options' => [
                '--no-save' => 'Do not save the generated application key to the .env file.',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit generate:key',
                'php novakit generate:key --no-save',
            ],
        ],

        'generate:sitemap' => [
            'name' => 'System',
            'group' => 'generate:sitemap',
            'description' => "Generates the application website sitemap.",
            'usages' => [
                'php novakit generate:sitemap',
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [],
        ],

        'env:add' => [
            'name' => 'System',
            'group' => 'env:add',
            'description' => "Adds or updates an environment variable with a new key and value.",
            'usages' => [
                'php novakit env:add',
            ],
            'options' => [
                '--key' => 'Specify the environment key name to add.',
                '--value' => 'Specify the environment key content value to add.',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit env:add --key="test.key" --value="test key value"',
            ],
        ],

        'env:cache' => [
            'name' => 'System',
            'group' => 'env:cache',
            'description' => "Create or recreate a cache version of environment variables for production.",
            'usages' => [
                'php novakit env:cache',
            ],
            'options' => [
                '-i, --ignore'   => 'Specify optional keys to ignore.',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit env:cache',
                'php novakit env:cache --ignore="app.name,foo.bar"',
            ],
        ],

        'env:setup' => [
            'name' => 'System',
            'group' => 'env:setup',
            'description' => "Adds or updates environment variables for a specific context.\nThis command simplifies configuring all required variables for supported contexts.",
            'usages' => [
                'php novakit env:setup -t=<TARGET>',
            ],
            'options' => [
                '-t, --target' => 'Specify the environment context (e.g., `database`, `telegram`).',
                '--token' => 'Set the Telegram bot token.',
                '--chatid' => 'Set the Telegram bot chat ID.',
                '-h, --help' => 'Show help information.',
            ],
            'examples' => [
                'php novakit env:setup --target=database',
                'php novakit env:setup --target=telegram',
            ],
        ],

        'env:remove' => [
            'name' => 'System',
            'group' => 'env:remove',
            'description' => "Removes an environment variable key from the '.env' file.",
            'usages' => [
                'php novakit env:remove --key="test.key"',
            ],
            'options' => [
                '--key' => 'Specify the environment key name to remove.',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit env:remove --key="test.key"',
            ],
        ],

        'context' => [
            'name' => 'Context',
            'group' => 'context',
            'description' => "Install application route context or create routes from defined route annotation attributes.",
            'usages' => [
                'php novakit context <context-name>',
                'php novakit context --export-attr',
            ], 
            'options' => [
                '-e, --export-attr' => 'Indicate to export and build code-based routes from defined route attributes.',
                '-c, --clear-attr' => 'Indicate to clear all cached route attributes to allow re-caching new changed.',
                '-n, --no-error' => 'Indicate to leave error callback handler `NULL` while adding new context.',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit context "foo"',
                'php novakit context "foo" --no-error',
                'php novakit context --export-attr',
            ],
        ],

       'create:controller' => [
            'name' => 'Generators',
            'group' => 'create:controller',
            'description' => 'Generate and install a new controller class. Optionally implement an interface, create a template view, or target a specific module.',
            'usages' => [
                'php novakit create:controller <ClassName>'
            ],
            'options' => [
                '--type' => 'Define the controller type: "view", "command", or "console".',
                '-i, --implement' => 'Specify one or more interface class names to implement (comma-separated).',
                '-t, --template' => 'Generate a template view file. Optionally specify a subdirectory under "Views/".',
                '-m, --module' => 'Set the HMVC module name (e.g. "Blog", "Admin").',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit create:controller TestController' =>
                    'Create a standard MVC or HMVC root controller class.',
                
                'php novakit create:controller TestController --module=FooModule' =>
                    'Create a controller class inside the "FooModule" HMVC module.',
                
                'php novakit create:controller TestController --implement="\\Foo\\Bar\\Interface, FooInterface"' =>
                    'Create a controller that implements one or multiple interfaces.',
                
                'php novakit create:controller CommandController --type=command' =>
                    'Create a CLI command controller.',
                
                'php novakit create:controller TestController --type=view --template' =>
                    'Create a view controller with a default template in "/resource/Views/" or "/app/Modules/Views/".',
                
                'php novakit create:controller TestController --type=view --template=Test' =>
                    'Create a view controller with a template in a specific subdirectory like "/Views/Test".',
            ],
        ],

       'create:view' => [
            'name' => 'Generators',
            'group' => 'create:view',
            'description' => 'Generate a new template view file (".php" or ".tpl") based on the template engine configuration in "/app/Config/Template.php".',
            'usages' => [
                'php novakit create:view <viewName>',
            ],
            'options' => [
                '-d, --dir' => 'Specify a subdirectory under "/resources/Views/" (MVC) or "/app/Modules/<?Module>/Views/" (HMVC) to store the view file.',
                '-m, --module' => 'Set the name of the HMVC module to target (e.g. "Blog", "Admin").',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit create:view TestView' =>
                    'Create a view file in the root "Views" directory.',
                
                'php novakit create:view TestView --module=FooModule' =>
                    'Create a view file in the "Views" directory of the specified HMVC module.',
                
                'php novakit create:view TestView --dir=layouts' =>
                    'Create a view file inside the "Views/layouts/" subdirectory.',
            ],
        ],

        'create:class' => [
            'name' => 'Generators',
            'group' => 'create:class',
            'description' => 'Generate a new class file and save it to the `/app/Utils/` directory.',
            'usages' => [
                'php novakit create:class <ClassName>',
            ],
            'options' => [
                '-e, --extend' => 'Specify a base class to extend.',
                '-i, --implement' => 'Specify one or more interfaces to implement (comma-separated).',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit create:class TestClass' =>
                    'Create a basic utility class.',
                
                'php novakit create:class TestClass --extend=FooBaseClass' =>
                    'Create a class that extends `FooBaseClass`.',
                
                'php novakit create:class TestClass --implement=FooInterface,BarInterface' =>
                    'Create a class that implements one or more interfaces.',
            ],
        ],

        'create:model' => [
            'name' => 'Generators',
            'group' => 'create:model',
            'description' => 'Generate a new database model class that extends `Luminova\Base\BaseModel`. The file is saved in `/app/Models/` for MVC or `/app/Modules/<?Module>/Models/` for HMVC.',
            'usages' => [
                'php novakit create:model <ModelClassName>'
            ],
            'options' => [
                '-m, --module' => 'Specify the HMVC module name (e.g. "Blog", "Admin").',
                '-i, --implement' => 'List one or more interfaces to implement (comma-separated).',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit create:model TestModel' =>
                    'Create a model class in the default MVC `/app/Models/` or root HMVC `/app/Modules/Models/` directory.',

                'php novakit create:model TestModel --module=Foo' =>
                    'Create a model class in the HMVC module `/app/Modules/Foo/Models/`.',

                'php novakit create:model TestModel --implement=TestModelInterface' =>
                    'Create a model class and implement the `TestModelInterface`.',
            ],
        ],

        'db:drop' => [
            'name' => 'Database',
            'group' => 'db:drop',
            'description' => "Drops the database migration table.",
            'usages' => [
                'php novakit db:drop',
                'php novakit db:drop --class=TestMigration',
            ],
            'options' => [
                '-c, --class' => "Specify the migration class to drop.",
                '-n, --no-backup' => "Run migration drop without backup.",
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit db:drop --class=TestMigration',
                'php novakit db:drop --class=TestMigration --no-backup',
                'php novakit db:drop --no-backup',
            ],
        ],

        'db:clear' => [
            'name' => 'Database',
            'group' => 'db:clear',
            'description' => "Clears lock files for seeders or migrations.",
            'usages' => [
                'php novakit db:clear --lock=seeder',
                'php novakit db:clear --lock=migration',
                'php novakit db:clear --lock=migration --class=TestMigration',
            ],
            'options' => [
                '-l, --lock' => "Specify the context of lock files to clear. Allowed values: 'seeder', 'migration'.",
                '-c, --class' => "Specify the migration or seeder class to clear lock files for.",
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit db:clear --lock=seeder',
                'php novakit db:clear --lock=migration',
                'php novakit db:clear --lock=migration --class=TestMigration',
            ],
        ],

        'db:alter' => [
            'name' => 'Database',
            'group' => 'db:alter',
            'description' => "Alter database migration tables and columns.",
            'usages' => [
                'php novakit db:alter',
                'php novakit db:alter --class=TestMigration',
            ],
            'options' => [
                '-c, --class' => "Specify the migration class to alter.",
                '-n, --no-backup' => "Run alter migration without considering locked version nor creating a new backup lock version.",
                '-d, --drop-columns' => "Drop columns that don't exist in the new schema during the alter migration.",
                '-b, --debug' => "Print generated alteration SQL query string without applying any changes.",
                '-h, --help' => 'Show help information for this command.'
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
            'name' => 'Database',
            'group' => 'db:truncate',
            'description' => "Truncates a database table to clear all records.",
            'usages' => [
                'php novakit db:truncate --table=<table-name>',
            ],
            'options' => [
                '-t, --table' => "Specify the database table name to truncate.",
                '-n, --no-transaction' => "Run database truncation without transaction.",
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit db:truncate --table=TestTable',
                'php novakit db:truncate --table=TestTable --no-transaction',
            ],
        ],

        'db:seed' => [
            'name' => 'Database',
            'group' => 'db:seed',
            'description' => "Executes database seeders.",
            'usages' => [
                'php novakit db:seed' => 'Executes all database seeders classes.',
                'php novakit db:seed --class=<class-name>' => 'Execute a specific database seeder class.'
            ],
            'options' => [
                '-c, --class' => "Specify the seeder class to run.",
                '-t, --table' => "Specify the seeder class table name to truncate before rolling back.",
                '-r, --rollback' => "Rollback seeder to previous version.",
                '-n, --no-backup' => "Run seeder without considering locked version nor creating a new backup lock version.",
                '-i, --invoke' => "Run seeder and also invoke other invokable seeders classes.",
                '-h, --help' => 'Show help information for this command.'
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
            'name' => 'Database',
            'group' => 'db:migrate',
            'description' => 'Executes database table migrations.',
            'usages' => [
                'php novakit db:migrate' => 'Executes all database migration classes.',
                'php novakit db:migrate --class=<class-name>' => 'Execute a specific database migration class.'
            ],
            'options' => [
                '-c, --class' => "Specify the migration class to run.",
                '-n, --no-backup' => "Run migration without considering locked version nor creating a new backup lock version.",
                '-d, --drop' => "Drop table for `down` method during migration.",
                '-r, --rollback' => "Rollback migration to previous version.",
                '-b, --debug' => "Print generated migration SQL query string without applying any changes.",
                '-i, --invoke' => "Run migration and also invoke other invokable migration classes.",
                '-h, --help' => 'Show help information for this command.'
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
            'name' => 'Cron',
            'group' => 'cron:create',
            'description' => "Creates cron tasks and locks them in the cron lock file.",
            'usages' => [
                'php novakit cron:create'
            ],
            'options' => [
                '-f, --force'  => 'Force update locked tasks with new changes from cron class if already locked.',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit cron:create',
                'php novakit cron:create --force',
            ],
        ],

        'cron:run' => [
            'name' => 'Cron',
            'group' => 'cron:run',
            'description' => "Runs cron jobs that were locked in the cron lock file.",
            'usages' => [
                'php novakit cron:run'
            ],
            'options' => [
                '-f, --force'  => 'Force update locked tasks with new changes from cron class if already locked.',
                '-s, --sleep' => 'The number of seconds to delay between task executions (default: 100000).',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit cron:run',
                'php novakit cron:run --force',
                'php novakit cron:run --sleep=100000'
            ],
        ],

        'cache' => [
            'name' => 'Cache',
            'group' => 'cache',
            'description' => "Manages system caches: clears, deletes by key, or lists cache items.",
            'usages' => [
                'php novakit cache:clear',
                'php novakit cache:clear --key=TestKey',
                'php novakit cache:list',
            ],
            'options' => [
                '--key'  => 'Specify the cache key to delete.',
                '--storage'  => 'Specify the cache storage name to delete.',
                '-h, --help' => 'Show help information for this command.'
            ],
            'examples' => [
                'php novakit cache:clear',
                'php novakit cache:clear --key=TestKey',
                'php novakit cache:list',
            ],
        ],

        'clear:caches' => [
            'name' => 'Clear',
            'group' => 'clear:caches',
            'description' => 'Clears cached pages, database cache files, and route files from the writeable caches directory. If no directory is specified, all cached files will be cleared.',
            'usages' => [
                'php novakit clear:caches',
            ],
            'options' => [
                '-d, --dir=<name>' => 'Specify a subdirectory within caches to clear (e.g., routes).',
                '-h, --help' => 'Displays help for this command.',
            ],
            'examples' => [
                'php novakit clear:caches --dir=routes' => 'Clears cached route files in the writeable caches directory.',
            ],
        ],

        'clear:routes' => [
            'name' => 'Clear',
            'group' => 'clear:routes',
            'description' => 'Clear all cached route attributes files from the writeable routes directory.',
            'usages' => [
                'php novakit clear:routes',
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
            ],
            'examples' => [
                'php novakit clear:routes' => 'Clears cached route files.',
            ],
        ],

        'clear:storage' => [
            'name' => 'Clear',
            'group' => 'clear:storage',
            'description' => 'Clear all files in the writeable private storages directory.',
            'usages' => [
                'php novakit clear:storage',
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
            ],
            'examples' => [
                'php novakit clear:storage' => 'Clears all files in writeable storages directory.',
            ],
        ],

        'clear:temp' => [
            'name' => 'Clear',
            'group' => 'clear:temp',
            'description' => 'Clear all temporary files from the writeable temp directory.',
            'usages' => [
                'php novakit clear:temp',
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
            ],
            'examples' => [
                'php novakit clear:temp' => 'Clears all temporary files in writeable temp directory.',
            ],
        ],
        
        'clear:writable' => [
            'name' => 'Clear',
            'group' => 'clear:writable',
            'description' => 'Clear files and directories from the writeable directory.',
            'usages' => [
                'php novakit clear:writable',
            ],
            'options' => [
                '-d, --dir=<name>' => 'Specify the directory to clear in writeable.',
                '-p, --parent' => 'If set, removes the specified directory itself.',
                '-h, --help' => 'Show help information for this command.',
            ],
            'examples' => [
                'php novakit clear:writable --dir=temp' => 'Clears all files in the writeable/temp directory.',
                'php novakit clear:writable --dir=temp --parent' => 'Clears the writeable/temp directory and deletes the temp folder itself.',
            ],
        ],
    ];

    /**
     * Get all available commands.
     * 
     * @return array<string,array<string,mixed>> Return all available commands and their information.
     */
    public static function getCommands(): array 
    {
        self::$commands['help']['description'] = self::getDescription();
        return self::$commands;
    }

    /**
     * Get command information.
     * 
     * @param string $key The command group.
     * 
     * @return array<string,mixed> Return a specific command information.
     */
    public static function get(string $group): array 
    {
        return self::getCommands()[$group] ?? [];
    }

    /**
     * Check if command exists.
     * 
     * @param string $group The command group to check.
     * 
     * @return bool Return true if the command exists, false otherwise.
     */
    public static function has(string $group): bool
    {
        return self::get($group) !== [];
    }

    /**
     * Search the closest command match.
     *
     * This is responsible for providing suggestions for command groups 
     * based on a given input string. It utilizes the Levenshtein distance algorithm 
     * to find the closest match from a list of available commands, helping users 
     * identify the intended command when a typo or similar mistake is made.
     * 
     * @param string $input The user input to find a close match for.
     * 
     * @return string|null Return the closest matching command group, or null if no close match is found.
     */
    public static function search(string $input): ?string
    {
        $input = strtolower($input);
        $suggestion = null;
        $shortestDistance = -1;

        foreach (self::getCommands() as $command) {
            $group = strtolower($command['group']);

            if (str_starts_with($group, $input)) {
                return $command['group'];
            }

            $distance = levenshtein($input, $group);

            if ($distance === 0) {
                return $command['group'];
            }

            if ($distance < $shortestDistance || $shortestDistance < 0) {
                $suggestion = $command['group'];
                $shortestDistance = $distance;
            }
        }

        return $suggestion;
    }

    /**
     * Suggest a similar commands.
     * 
     * @param string $input The user input to suggest a close match for.
     * 
     * @return string Return a formatted suggestion string, or an empty string if no suggestion is found.
     */
    public static function suggest(string $input): string
    {
        $suggestion = self::search($input);
        return $suggestion 
            ? 'Do you mean "' . Color::style($suggestion, 'cyan') . '"?'
            : '';
    }

    /**
     * Format help command descriptions.
     * 
     * @return string Return formatted help command descriptions.
     */
    private static function getDescription(): string 
    {
        $title = Color::apply(
            "PHP Luminova Novakit Command Help (Novakit Version: " . Luminova::NOVAKIT_VERSION .
            ", Framework Version: " . Luminova::VERSION . ")",
            Text::FONT_BOLD, 'yellow'
        );
    
        $note = Color::apply('IMPORTANT NOTE:', Text::FONT_BOLD, 'red');
        $flags = Color::apply('--help (-h)', null, 'yellow');
    
        return <<<TEXT
            {$title}
            This command displays help options for the Novakit CLI tool.
            
            To execute `novakit` commands, run them from your application's root directory (e.g., 'php novakit command'). 
            For controller-related commands, navigate to the `public` directory before execution (e.g., 'php index.php command').
            
            {$note}
            The {$flags} options are reserved for displaying help messages and should not be used 
            for custom arguments when creating CLI applications.
        TEXT;
    }
}