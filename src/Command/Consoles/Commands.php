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
            'description' => 'Displays help information for novakit or application controller-based commands.',
            'usages' => [
                'php novakit <command> --help' => 'Display help for a specific NovaKit command.',
                'php novakit <group:namespace> --help' => 'Display help for a namespaced NovaKit group command.',
                'php novakit <group> --help' => 'Alternative syntax to list all commands under a NovaKit group.',
                'php index.php <command-group> --help' => 'Display help for a controller-based command (via index.php).',
                'php novakit list' => 'List all available NovaKit and controller commands.',
                'php novakit list --command=<name>' => 'List commands under a specific NovaKit group, namespace, or controller command group.',
                'php novakit <command> --foo=bar --baz' => 'Execute a NovaKit command with options or arguments.',
                'php novakit <group:namespace> --foo=bar --baz' => 'Execute a namespaced NovaKit command with options or arguments.',
                'php index.php <command-group> --foo=bar --baz' => 'Execute a routable controller command from index.php.',
            ],
            'options' => [
                '-h, --help' => 'Display help message related to NovaKit or controller command.',
                '--system-info' => 'Display basic system information (e.g. PHP version, OS, memory).',
                '-a, --all' => 'Show full list of available NovaKit commands and descriptions.',
                '--no-header' => 'Suppress the NovaKit header banner in output.',
                '--no-color' => 'Disable color formatting in the output.',
                '-v, --version' => 'Display the version of the framework and NovaKit CLI.',
            ],
            'examples' => [],
        ],

        'list' => [
            'name' => 'Lists',
            'group' => 'list',
            'description' => "Lists all available novakit commands and their descriptions.",
            'usages' => [
                'php novakit list',
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --command' => 'Optional. Specify command to list (e.g, task::list or task).'
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
            'name' => 'CronWorker',
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
            'name' => 'CronWorker',
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

        'task:init' => [
            'name' => 'TaskWorker',
            'group' => 'task:init',
            'description' => 'Initialize the task queue system and create the required table in your database. Used to initialize the task system.',
            'usages' => [
                'php novakit task:init'
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --class' => 'Optional. Fully qualified class name that implements the task queue. Defaults to App\\Tasks\\TaskQueue.',
            ],
            'examples' => [
                'php novakit task:init --class=App\\Tasks\\MyTask' => 'Creates a task queue table using your custom task class.',
            ],
        ],

        'task:deinit' => [
            'name' => 'TaskWorker',
            'group' => 'task:deinit',
            'description' => 'Drop the task queue table from the database. Removes all associated tasks.',
            'usages' => [
                'php novakit task:deinit'
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --class' => 'Optional. Fully qualified class name to identify the task table. Defaults to App\\Tasks\\TaskQueue.',
            ],
            'examples' => [
                'php novakit task:deinit --class=App\\Tasks\\MyTask' => 'Drops the task table defined in your custom task class.',
            ],
        ],

        'task:queue' => [
            'name' => 'TaskWorker',
            'group' => 'task:queue',
            'description' => 'Add a new task to the queue for later execution.',
            'usages' => [
                'php novakit task:queue -t=App\\Utils\\MyHandler@run -a=\'["param1", 2, true]\''
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --class'    => 'Optional. Task class used to queue tasks. Defaults to App\\Tasks\\TaskQueue.',
                '-t, --task' => 'Handler to execute: a function name, static method (Class::method), or instance method (Class@method). Optional if tasks are already queued via TaskQueue->tasks().',
                '-a, --args'     => 'Optional. JSON array of arguments to pass to the handler.',
                '-s, --schedule' => 'Optional. Delay the task execution. Accepts a UNIX timestamp, formatted date (Y-m-d H:i:s), or relative time (e.g., "+5 minutes").',
                '-p, --priority' => 'Optional. Task execution priority (0 = highest, 100 = lowest). Defaults to 0.',
                '-f, --forever' => 'Optional. Recheck interval in minutes (≥ 5) for forever tasks to run again after marked completed or failed.',
                '-r, --retries' => 'Optional. The number of times to retry task if failed (default: 0) Unlimited.',
            ],
            'examples' => [
                'php novakit task:queue -t=App\\Service@handle -a=\'["foo", 42]\''
                    => 'Queue a class method with parameters.',
            ],
        ],

        'task:list' => [
            'name' => 'TaskWorker',
            'group' => 'task:list',
            'description' => 'List tasks in the queue with optional filters.',
            'usages' => [
                'php novakit task:list --status=pending --limit=10 --offset=0'
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --class'  => 'Optional. Task queue class. Defaults to App\\Tasks\\TaskQueue.',
                '-s, --status' => 'Optional. Filter by task status: pending, running, completed, etc.',
                '-l, --limit'  => 'Optional. Maximum number of tasks to list.',
                '-o, --offset' => 'Optional. Number of tasks to skip.',
            ],
            'examples' => [
                'php novakit task:list --status=pending --limit=5' => 'Lists the first 5 pending tasks.',
            ],
        ],

        'task:export' => [
            'name' => 'TaskWorker',
            'group' => 'task:export',
            'description' => 'Export all tasks from the queue with optional status filtering.',
            'usages' => [
                'php novakit task:export --dir=path/to/export/tasks.php --status=pending'
            ],
            'options' => [
                '-h, --help'   => 'Show help information for this command.',
                '-c, --class'  => 'Optional. Task queue class. Defaults to App\\Tasks\\TaskQueue.',
                '-d, --dir'    => 'Required. File path to save the exported tasks.',
                '-s, --status' => 'Optional. Task status to filter (e.g., all, pending, failed). Default is "all".',
            ],
            'examples' => [
                'php novakit task:export --dir=path/to/export/tasks.php' => 'Export all tasks to the specified file.',
            ],
        ],

        'task:info' => [
            'name' => 'TaskWorker',
            'group' => 'task:info',
            'description' => 'View detailed information about a specific task by ID.',
            'usages' => [
                'php novakit task:info --id=42'
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --class' => 'Optional. Task queue class.',
                '-i, --id'    => 'Required. ID of the task to inspect.',
            ],
            'examples' => [
                'php novakit task:info --id=99' => 'Show detailed info of task #99.',
            ],
        ],

        'task:delete' => [
            'name' => 'TaskWorker',
            'group' => 'task:delete',
            'description' => 'Delete a specific task from the queue.',
            'usages' => [
                'php novakit task:delete --id=42'
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --class' => 'Optional. Task queue class.',
                '-i, --id'    => 'Required. ID of the task to delete.',
            ],
            'examples' => [
                'php novakit task:delete --id=15' => 'Remove task #15 from the database.',
            ],
        ],

        'task:purge' => [
            'name' => 'TaskWorker',
            'group' => 'task:purge',
            'description' => 'Clear all tasks of a given status (e.g., completed).',
            'usages' => [
                'php novakit task:purge --status=completed'
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --class'  => 'Optional. Task queue class.',
                '-s, --status' => 'Optional. Task status to clear. Defaults to all.',
            ],
            'examples' => [
                'php novakit task:purge --status=failed' => 'Remove all failed tasks.',
            ],
        ],

        'task:pause' => [
            'name' => 'TaskWorker',
            'group' => 'task:pause',
            'description' => 'Pause a running or pending task.',
            'usages' => [
                'php novakit task:pause --id=42'
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --class' => 'Optional. Task queue class.',
                '-i, --id'    => 'Required. ID of the task to pause.',
                '-p, --priority' => 'Optional. Task execution priority (0 = highest, 100 = lowest).',
            ],
            'examples' => [
                'php novakit task:pause --id=7' => 'Pause task #7 if supported.',
            ],
        ],

        'task:resume' => [
            'name' => 'TaskWorker',
            'group' => 'task:resume',
            'description' => 'Resume a previously paused task.',
            'usages' => [
                'php novakit task:resume --id=42'
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --class' => 'Optional. Task queue class.',
                '-i, --id'    => 'Required. ID of the paused task.',
            ],
            'examples' => [
                'php novakit task:resume --id=7' => 'Resume task #7.',
            ],
        ],

        'task:retry' => [
            'name' => 'TaskWorker',
            'group' => 'task:retry',
            'description' => 'Retry a failed task by marking it as pending.',
            'usages' => [
                'php novakit task:retry --id=42'
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --class' => 'Optional. Task queue class.',
                '-i, --id'    => 'Required. ID of the failed task to retry.',
            ],
            'examples' => [
                'php novakit task:retry --id=9' => 'Retry a failed task.',
            ],
        ],

        'task:sig' => [
            'name' => 'TaskWorker',
            'group' => 'task:sig',
            'description' => 'Send control signals to the task worker (stop or resume).',
            'usages' => [
                'php novakit task:sig --stop-worker',
                'php novakit task:sig --resume-worker',
            ],
            'options' => [
                '-h, --help'         => 'Show help information for this command.',
                '-c, --class'        => 'Optional. Task queue class. Defaults to App\\Tasks\\TaskQueue.',
                '-s, --stop-worker'  => 'Stop the worker by creating a signal lock file.',
                '-r, --resume-worker'=> 'Resume the worker by removing the signal lock file.',
            ],
            'examples' => [
                'php novakit task:sig --stop-worker'  => 'Tells the running task worker to gracefully shut down.',
                'php novakit task:sig --resume-worker'=> 'Allows a previously stopped worker to continue by removing the signal file.',
            ],
        ],

        'task:status' => [
            'name' => 'TaskWorker',
            'group' => 'task:status',
            'description' => 'Update the status of a specific task.',
            'usages' => [
                'php novakit task:status --id=42 --status=completed'
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --class'  => 'Optional. Task queue class.',
                '-i, --id'     => 'Required. Task ID to update.',
                '-s, --status' => 'Required. New status: pending, paused, running, completed, etc.',
            ],
            'examples' => [
                'php novakit task:status --id=42 --status=paused' => 'Manually set task #42 as paused.',
            ],
        ],

        'task:run' => [
            'name' => 'TaskWorker',
            'group' => 'task:run',
            'description' => 'Execute queued tasks in a worker loop. Can limit execution or auto-exit after inactivity.',
            'usages' => [
                'php novakit task:run --limit=10 --sleep=500000 --idle=5'
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --class'  => 'Optional. Task queue class.',
                '-o, --output' => 'Optional. Log output path or log level (e.g., debug).',
                '-s, --sleep'  => 'Optional. Microseconds to wait between tasks. Default: 100000 (0.1s).',
                '-l, --limit'  => 'Optional. Max number of tasks to process in one loop.',
                '-i, --idle'   => 'Optional. Max idle attempts before stopping.',
                '-f, --flock-worker' => 'Optional. Use a file lock to prevent multiple worker instances from running at the same time.',
            ],
            'examples' => [
                'php novakit task:run --output=debug --limit=5' => 'Run and log 5 tasks with debug output.',
            ],
        ],

        'task:listen' => [
            'name' => 'TaskWorker',
            'group' => 'task:listen',
            'description' => 'Listen for new task events written to the task log file. Useful for real-time CLI monitoring.',
            'usages' => [
                'php novakit task:listen'
            ],
            'options' => [
                '-h, --help' => 'Show help information for this command.',
                '-c, --class' => 'Optional. Task queue class with logEvents file path set.',
            ],
            'examples' => [
                'php novakit task:listen --class=App\\Tasks\\MyTask' => 'Listen to events from a custom task class.',
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
     * Get help examples of all commands.
     * 
     * @return array
     */
    public static function getGlobalHelps(?string $group = null, ?int &$largest = null): array 
    {
        $examples = [];
        $last = 0;

        foreach (self::$commands as $command => $value) {
            if ($command === 'help' || ($group && !str_starts_with($command, $group))) {
                continue;
            }

            $name = strstr($command, ':', true) ?: $command;
            $key = "php novakit $command --help";

            if($largest !== null){
                $length = strlen($key);

                if($length > $last){
                    $largest = $length;
                }
            }

            $examples[$key] = $group 
                ? ($value['description'] ?? 'Show available command usage and help.')
                : sprintf(
                    "Display help for %s command group: %s",
                    $command,
                    $name
                );
            }

        if(!$group){
            $examples['php index.php CommandGroup --help'] = 'Display help for routable CLI controller commands.';
        }

        return $examples;
    }

    /**
     * Format help command descriptions.
     * 
     * @return string Return formatted help command descriptions.
     */
    private static function getDescription(): string 
    {
        $title = Color::apply(
            " PHP Luminova Novakit Command Help (Novakit Version: " . Luminova::NOVAKIT_VERSION .
            ", Framework Version: " . Luminova::VERSION . ")",
            Text::FONT_BOLD, 'brightBlack'
        );
    
        $note = Color::apply('IMPORTANT NOTE:', Text::FONT_BOLD, 'red');
        $flags = Color::apply('--help (-h)', null, 'yellow');
    
        return <<<TEXT
            {$title}\n
            This command displays help options for the Novakit CLI tool.
            
            To execute `novakit` commands, run them from your application's root directory (e.g., 'php novakit command'). 
            For controller-related commands, navigate to the `public` directory before execution (e.g., 'php index.php command').
            
            {$note}
            The {$flags} options are reserved for displaying help messages and should not be used 
            for custom arguments when creating CLI applications.
        TEXT;
    }
}