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

use Luminova\Command\Utils\Text;
use Luminova\Command\Utils\Color;

trait CommandsTrait 
{
    /**
     * @var string THIS_HELP
     */
    private const THIS_HELP = 'Display help information for this command.';

    /**
     * @var string TASK_Q_CLASS_DESCRIPTION
     */
    private const TASK_Q_CLASS_DESCRIPTION = 'Optional. Task queue class to manage. Supports a fully qualified class name or a class name in `App\Tasks` (default: App\\Tasks\\TaskQueue).';

    /**
     * @var array DEFAULT_OPTIONS
     */
    private const DEFAULT_OPTIONS = [
        '--no-header'  => 'Hide the novakit header banner.',
        '--no-color'   => 'Disable colored output.',
        '--no-ansi'    => 'Disable ANSI output formatting.',
        '-h, --help'   => self::THIS_HELP,
    ];

    /**
     * Commands initialization flag.
     *
     * @var bool $initialized
     */
    private static bool $initialized = false;

     /**
     * All available commands.
     *
     * @var array{
     *     name:string,
     *     group:string,
     *     description:string,
     *     aliases?:array<int,string>,
     *     usages:array<int|string,string>,
     *     options:array<string,string>,
     *     examples:array<int|string,string>,
     *     deprecated:bool
     * }[] $commands
     */
    private static array $commands = [];

    /**
     * Initialize the NovaKit command registry.
     *
     * Registers all default commands on the first call and prevents
     * duplicate command definitions from being loaded.
     *
     * @return void
     */
    public static function register(): void
    {
        if (self::$initialized) {
            return;
        }

        self::registerAllDefault();
        self::$initialized = true;
    }

    /**
     * Register a CLI command definition.
     *
     * @param string $command Command identifier used to invoke the command
     *                       (e.g., `foo` in `php novakit foo --do`).
     * @param string $name Display name of the command.
     * @param string $description Command description shown in help output.
     * @param array<int,string> $aliases Alternative command names.
     * @param array<int|string,string> $usages Command usage examples.
     * @param array<string,string> $options Command options and descriptions.
     * @param array<int|string,string> $examples Command execution examples.
     * @param string|null $group Command group name. Defaults to the command name.
     * @param bool $deprecated Whether the command is deprecated.
     * @param bool $returnDefinition Whether to return the registered definition.
     *
     * @return array{
     *     name:string,
     *     group:string,
     *     description:string,
     *     aliases?:array<int,string>,
     *     usages:array<int|string,string>,
     *     options:array<string,string>,
     *     examples:array<int|string,string>,
     *     deprecated:bool
     * }|null Registered command definition when requested.
     */
    public static function add(
        string $command,
        string $name,
        string $description,
        array $aliases = [],
        array $usages = [],
        array $options = [],
        array $examples = [],
        ?string $group = null,
        bool $deprecated = false,
        bool $returnDefinition = false
    ): ?array 
    {
        if ($deprecated) {
            $prefix = Color::apply('[Deprecated]', Text::FONT_BOLD, 'red');

            $description = "{$prefix} {$description}";
        }

        $cmd = [
            'name'        => $name,
            'group'       => $group ?? $command,
            'description' => $description,
            'aliases'     => [],
            'usages'      => $usages,
            'options'     => $options,
            'examples'    => $examples,
            'deprecated'  => $deprecated,
        ];

        if ($aliases !== []) {
            $cmd['aliases'] = $aliases;
        }else{
            unset($cmd['aliases']);
        }

        $cmd['options'] += self::DEFAULT_OPTIONS;

        self::$commands[$command] = $cmd;

        return $returnDefinition ? $cmd : null;
    }

    /**
     * Register all built-in NovaKit command definitions.
     *
     * This method registers the default commands provided by NovaKit.
     * It is called during command registry initialization and should only
     * be executed once.
     *
     * @return void
     */
    private static function registerAllDefault(): void 
    {
        self::add(
            'help',
            'Help',
            'Display help information for novakit and controller-based commands.',
            usages: [
                'php novakit <command> --help' =>
                    'Display help for a specific NovaKit command.',
                'php novakit <group:namespace> --help' =>
                    'Display help for a namespaced NovaKit command group.',
                'php novakit <group> --help' =>
                    'Display help for all commands in a group.',
                'php index.php <command-group> --help' =>
                    'Display help for a controller command group.',
                'php novakit list' =>
                    'List all available NovaKit and controller commands.',
                'php novakit list --command=<name>' =>
                    'List commands in a specific group or namespace.',
                'php novakit <command> --foo=bar --baz' =>
                    'Execute a NovaKit command with options or arguments.',
                'php novakit <group:namespace> --foo=bar --baz' =>
                    'Execute a namespaced NovaKit command with options or arguments.',
                'php index.php <command-group> --foo=bar --baz' =>
                    'Execute a controller command through index.php.',
            ],
            options: [
                '--system-info' => 'Display system information (PHP version, OS, memory, etc.).',
                '-a, --all' => 'Show all available commands and descriptions.',
                '-v, --version' => 'Display framework and NovaKit versions.',
            ],
        );

        self::add(
            'list',
            'List Commands',
            'Display available novakit and application commands with descriptions.',
            usages: [
                'php novakit list',
            ],
            options: [
                '-c, --command' => 'Optional. Specify a command to display (e.g., task::list or task).',
            ],
            examples: [
                'php novakit list -c=task',
                'php novakit list -c=task::list',
            ]
        );

        self::add(
            'async',
            'Async Task',
            'Execute framework-generated asynchronous tasks in the background.'
            . "\n- Use Luminova\\Components\\Async::background() to start background tasks."
            . "\n- Use Luminova\\Components\\Future\\ProcessFuture to create custom background tasks.",
            usages: [
                'php novakit async [options]',
            ],
            options: [
                '--arguments' => 'Required. Base64-encoded serialized task arguments.',
                '--handler'   => 'Required. Serialized task handler type (closure, opis.closure, callable, array, or php).',
                '--task'      => 'Required. Base64-encoded serialized task.',
                '--awaitable' => 'Optional. Wait for task completion (default: false).',
                '--log'       => 'Optional. Log channel or level (default: background).',
                '--pid-pipe'  => 'Optional. Windows named pipe for PID communication.',
            ],
            examples: [
                'php novakit async --arguments=... --handler=closure --task=...',
            ]
        );

        self::add(
            'auth',
            'Authentication',
            'Authenticate CLI users using a username and key or password.',
            usages: [
                'php novakit auth login --user',
                'php novakit auth logout',
            ],
            options: [
                '-u, --user'        => 'Username used for key or password authentication.',
                '-s, --silent-login' => 'Suppress the successful login message.',
            ],
            examples: [
                'php novakit auth login -u="username"' => 'Authenticate the specified user.',
            ]
        );

        self::add(
            'log',
            'Logs',
            'Manage application logs. Allow you to read, manage, and clear application log files.',
            [
                'php novakit log',
            ],
            options: [
                '-l, --level' => 'Specify the log level to read or clear (e.g., notice, debug).',
                '-s, --start' => 'Specify the starting line offset when reading logs.',
                '-e, --end'   => 'Specify the maximum number of log lines to display.',
                '-c, --clear' => 'Clear the specified log level.',
            ],
            examples: [
                'php novakit log --level=notice --start=20 --end=50' => 'Display 50 log lines starting from line 20.',
                'php novakit log --level=notice --end=10' => 'Display the 10 most recent log lines.',
                'php novakit log --level=debug --clear' => 'Clear all logs for the specified level.',
            ]
        );


        self::add(
            'server',
            'Server',
            description: "Start the Luminova PHP development server using PHP's built-in HTTP server.\n"
                . "Bind to a custom host or local network address for testing on other devices.",
            aliases: ['http', 'serve'],
            usages: [
                'php novakit server',
            ],
            options: [
                '-b, --php'     => 'Path to a custom PHP binary.',
                '-a, --host'    => 'Hostname or IP address to bind the server.',
                '-p, --port'    => 'Starting port (default: 8080).',
                '-r, --retry'   => 'Retry attempts when the port is unavailable (default: 5).',
                '-t, --testing' => 'Bind to the local network address for access from other devices.',
                '-j, --json'    => 'Output server details in JSON format.',
            ],
            examples: [
                'php novakit server' => 'Start the development server with default settings.',
                'php novakit server --port=8080 --testing' =>
                    'Start the server on port 8080 and allow network access.',
                'php novakit server --host=localhost --port=8080 --php=/path/to/php' =>
                    'Start the server with a custom PHP binary and host.',
                'php novakit server --port=8080 --retry=10' =>
                    'Retry up to 10 times if the port is unavailable.',
                'php novakit server --json' =>
                    'Output server details for scripts and automation.',
            ],
        );

        self::add(
            'project',
            'Project',
            'Prepare the application for production by exporting, importing, or archiving project files.',
            usages: [
                'php novakit project',
            ],
            options: [
                '-e, --export'   => 'Export production-ready files to the build directory.',
                '-i, --import'   => 'Import project files into the current directory.',
                '-a, --archive'  => 'Create a ZIP archive of production-ready files.',
                '-d, --dir'      => 'Source directory path for importing files.',
                '-p, --progress' => 'Display build progress.',
                '-q, --quiet'    => 'Suppress output except errors.',
                '-v, --verbose'  => 'Increase output verbosity (repeat for more detail).',
            ],
            examples: [
                'php novakit project --archive' =>
                    'Create a production ZIP archive.',
                'php novakit project --export' =>
                    'Export production-ready application files.',
                'php novakit project --import --dir=/path/to/project' =>
                    'Import files from another project directory.',
                'php novakit project -a -vv' =>
                    'Create an archive with verbose output.',
            ],
        );

        self::add(
            'generate:key',
            'System',
            'Generate an application encryption key and optionally save it to the environment file.',
            usages: [
                'php novakit generate:key',
            ],
            options: [
                '-n, --no-save' => 'Generate the key without saving it to .env as "app.key".',
                '-l, --length'  => 'Set a custom key length. Defaults to the encryption method requirement.',
                '-p, --prefix'  => 'Add a key prefix (for example: "sk-").',
            ],
            examples: [
                'php novakit generate:key',
                'php novakit generate:key --no-save',
                'php novakit generate:key --length=36 --prefix=sk- --no-save',
            ],
        );

        self::add(
            'generate:sitemap',
            'System',
            description: "Use 'php novakit sitemap' instead.",
            usages: [
                'php novakit generate:sitemap',
            ],
            deprecated: true
        );


        self::add(
            'sitemap',
            'Sitemap',
            'Generate an XML sitemap or scan a website for broken links.',
            usages: [
                'php novakit sitemap --url=<start-url>' =>
                    'Generate a sitemap from the specified URL.',
                'php novakit sitemap --url=<start-url> --broken' =>
                    'Scan for broken links instead of generating a sitemap.',
                'php novakit sitemap --url=<start-url> --basename=custom.xml' =>
                    'Generate a sitemap with a custom file name.',
            ],
            options: [
                '-u, --url' => "Starting URL to scan (default: env(dev.app.start.url)).",
                '-f, --basename' => 'Output file name (default: sitemap.xml or broken.sitemap.json).',
                '-b, --broken' => 'Generate a JSON broken-link report instead of an XML sitemap.',
                '-t, --link-tree' => 'Generate a text report containing all discovered links.',
                '-dx, --desc-xpath' => 'XPath selector for extracting page descriptions (e.g., //p[@class="intro"]).',
                '--format' => 'Custom link-tree output format (e.g., "{url} | {title} | {status}").',
                '-l, --limit' => 'Maximum URLs to scan (0 = unlimited).',
                '-d, --delay' => 'Delay between URL scans in seconds (minimum: 1).',
                '-e, --max-execution' => 'Maximum execution time in seconds (0 = unlimited).',
                '-p, --prefix' => 'Only scan URLs matching this prefix.',
                '-c, --change' => 'Set sitemap change frequency (always, daily, weekly, etc.).',
                '-s, --html' => 'Include static .html versions of URLs in the sitemap.',
                '-v, --verbose' => 'Increase output verbosity (repeat for more detail).',
                '-n, --dry-run' => 'Scan without writing output files.',
                '-a, --ignore-assets' => 'Ignore URLs pointing to asset directories.',
            ],
            examples: [
                "\033[1;36mSitemap Generation\033[0m",
                'php novakit sitemap',
                'php novakit sitemap --url=https://example.com --basename=new_sitemap.xml',
                'php novakit sitemap --url=https://example.com --limit=100',
                'php novakit sitemap --url=https://example.com --delay=2 --max-execution=600',
                'php novakit sitemap --url=https://example.com --prefix=blog --html',
                'php novakit sitemap --url=https://example.com --change=daily',
                '',
                "\033[1;36mBroken Link Scan\033[0m",
                'php novakit sitemap --broken',
                'php novakit sitemap --url=https://example.com --broken --basename=scan.json',
                'php novakit sitemap --url=https://localhost --broken --limit=50',
                '',
                "\033[1;36mLink Tree Scan\033[0m",
                'php novakit sitemap -t --format "{url} | {title}"',
                'php novakit sitemap -t --format "{url} | {title} | {description}" --desc-xpath="//p[@aria-label=\'Subheading for this page\']"',
            ],
        );

        self::add(
            'env:add',
            'System',
            'Add or update an environment variable.',
            usages: [
                'php novakit env:add',
            ],
            options: [
                '--key' => 'Environment variable key name.',
                '--value' => 'Environment variable value.'
            ],
            examples: [
                'php novakit env:add --key="test.key" --value="test key value"',
            ],
        );

        self::add(
            'env:cache',
            'System',
            'Create or rebuild the production environment variable cache.',
            usages: [
                'php novakit env:cache',
            ],
            options: [
                '-i, --ignore' => 'Comma-separated environment keys to ignore.',
            ],
            examples: [
                'php novakit env:cache',
                'php novakit env:cache --ignore="app.name,foo.bar"',
            ],
        );

        self::add(
            'env:setup',
            'System',
            description: "Configure environment variables for a specific context.\n"
                . 'Supports setting required variables for supported integrations.',
            usages: [
                'php novakit env:setup -t=<TARGET>',
            ],
            options: [
                '-t, --target' => 'Environment context (e.g., database, telegram).',
                '--token' => 'Telegram bot token.',
                '--chat-id' => 'Telegram bot chat ID.',
            ],
            examples: [
                'php novakit env:setup --target=database',
                'php novakit env:setup --target=telegram',
            ],
        );

        self::add(
            'env:remove',
            'System',
            'Remove an environment variable from the .env file.',
            usages: [
                'php novakit env:remove --key="test.key"',
            ],
            options: [
                '--key' => 'Environment variable key name to remove.',
            ],
            examples: [
                'php novakit env:remove --key="test.key"',
            ],
        );


        self::add(
            'context',
            'Context',
            'Install application route contexts or generate routes from route annotation attributes.',
            usages: [
                'php novakit context <context-name>',
                'php novakit context --export-attr',
            ],
            options: [
                '-e, --export-attr' => 'Export and build routes from defined route attributes.',
                '-c, --clear-attr' => 'Clear cached route attributes before rebuilding.',
                '-n, --no-error' => 'Leave the error callback handler as NULL when creating a context.'
            ],
            examples: [
                'php novakit context "foo"',
                'php novakit context "foo" --no-error',
                'php novakit context --export-attr',
            ],
        );

        self::add(
            'create:controller',
            'Generators',
            description: "Generate and install a controller class.\n"
                . 'Supports interfaces, template views, and HMVC modules.',
            usages: [
                'php novakit create:controller <ClassName>',
            ],
            options: [
                '--type' => 'Controller type: view, command, or console.',
                '-i, --implement' => 'Interfaces to implement (comma-separated).',
                '-t, --template' => 'Generate a template view. Optionally specify a view subdirectory.',
                '-m, --module' => 'Target HMVC module name (e.g., Blog, Admin).',
            ],
            examples: [
                'php novakit create:controller TestController' =>
                    'Create a standard MVC or HMVC controller.',
                
                'php novakit create:controller TestController --module=FooModule' =>
                    'Create a controller inside an HMVC module.',
                
                'php novakit create:controller TestController --implement="\\Foo\\Bar\\Interface,FooInterface"' =>
                    'Create a controller implementing one or more interfaces.',
                
                'php novakit create:controller CommandController --type=command' =>
                    'Create a CLI command controller.',
                
                'php novakit create:controller TestController --type=view --template' =>
                    'Create a view controller with a default template.',
                
                'php novakit create:controller TestController --type=view --template=Test' =>
                    'Create a view controller with a template subdirectory.',
            ],
        );

        self::add(
            'create:view',
            'Generators',
            'Generate a template view file based on the configured template engine.',
            usages: [
                'php novakit create:view <viewName>',
            ],
            options: [
                '-d, --dir' => 'View subdirectory under the application Views directory.',
                '-m, --module' => 'Target HMVC module name (e.g., Blog, Admin).',
            ],
            examples: [
                'php novakit create:view TestView' =>
                    'Create a view file in the root Views directory.',
                
                'php novakit create:view TestView --module=FooModule' =>
                    'Create a view file inside an HMVC module Views directory.',
                
                'php novakit create:view TestView --dir=layouts' =>
                    'Create a view file inside the layouts subdirectory.',
            ],
        );

        self::add(
            'create:class',
            'Generators',
            'Generate a class file in the /app/Utils/ directory.',
            usages: [
                'php novakit create:class <ClassName>',
            ],
            options: [
                '-e, --extend' => 'Base class to extend.',
                '-i, --implement' => 'Interfaces to implement (comma-separated).',
            ],
            examples: [
                'php novakit create:class TestClass' =>
                    'Create a basic utility class.',
                
                'php novakit create:class TestClass --extend=FooBaseClass' =>
                    'Create a class extending FooBaseClass.',
                
                'php novakit create:class TestClass --implement=FooInterface,BarInterface' =>
                    'Create a class implementing one or more interfaces.',
            ],
        );


        self::add(
            'create:model',
            'Generators',
            description: "Generate a database model class extending `Luminova\\Base\\Model`.\n"
                . 'Stores files in /app/Models/ (MVC) or /app/Modules/<Module>/Models/ (HMVC).',
            usages: [
                'php novakit create:model <ModelClassName>',
            ],
            options: [
                '-m, --module' => 'HMVC module name (e.g., Blog, Admin).',
                '-i, --implement' => 'Interfaces to implement (comma-separated).',
            ],
            examples: [
                'php novakit create:model TestModel' =>
                    'Create a model in the default Models directory.',
                
                'php novakit create:model TestModel --module=Foo' =>
                    'Create a model inside the Foo HMVC module.',
                
                'php novakit create:model TestModel --implement=TestModelInterface' =>
                    'Create a model implementing the specified interface.',
            ],
        );

        self::add(
            'db:drop',
            'Database',
            'Drop the database migration table.',
            usages: [
                'php novakit db:drop',
                'php novakit db:drop --class=TestMigration',
            ],
            options: [
                '-c, --class' => 'Migration class to drop.',
                '-n, --no-backup' => 'Drop migration without creating a backup lock.',
            ],
            examples: [
                'php novakit db:drop --class=TestMigration',
                'php novakit db:drop --class=TestMigration --no-backup',
                'php novakit db:drop --no-backup',
            ],
        );

        self::add(
            'db:clear',
            'Database',
            'Clear migration or seeder lock files.',
            usages: [
                'php novakit db:clear --lock=seeder',
                'php novakit db:clear --lock=migration',
                'php novakit db:clear --lock=migration --class=TestMigration',
            ],
            options: [
                '-l, --lock' => "Lock type to clear: seeder or migration.",
                '-c, --class' => 'Migration or seeder class to clear.',
            ],
            examples: [
                'php novakit db:clear --lock=seeder',
                'php novakit db:clear --lock=migration',
                'php novakit db:clear --lock=migration --class=TestMigration',
            ],
        );

        self::add(
            'db:alter',
            'Database',
            'Alter database migration tables and columns.',
            usages: [
                'php novakit db:alter',
                'php novakit db:alter --class=TestMigration',
            ],
            options: [
                '-c, --class' => 'Migration class to alter.',
                '-n, --no-backup' => 'Alter migration without checking locked versions or creating a backup lock.',
                '-d, --drop-columns' => 'Drop columns removed from the new schema.',
                '-b, --debug' => 'Print generated SQL queries without applying changes.',
            ],
            examples: [
                'php novakit db:alter --class=TestMigration',
                'php novakit db:alter --class=TestMigration --no-backup',
                'php novakit db:alter --no-backup',
                'php novakit db:alter --class=TestMigration --drop-columns',
                'php novakit db:alter --drop-columns',
            ],
        );

        self::add(
            'db:truncate',
            'Database',
            'Truncate a database table and remove all records.',
            usages: [
                'php novakit db:truncate --table=<table-name>',
            ],
            options: [
                '-t, --table' => 'Database table name to truncate.',
                '-n, --no-transaction' => 'Truncate without using a transaction.',
            ],
            examples: [
                'php novakit db:truncate --table=TestTable',
                'php novakit db:truncate --table=TestTable --no-transaction',
            ],
        );

        self::add(
            'db:seed',
            'Database',
            'Execute database seeders.',
            usages: [
                'php novakit db:seed' => 'Execute all database seeder classes.',
                'php novakit db:seed --class=<class-name>' => 'Execute a specific seeder class.',
            ],
            options: [
                '-c, --class' => 'Seeder class to execute.',
                '-t, --table' => 'Seeder table name to truncate before rollback.',
                '-r, --rollback' => 'Rollback the seeder to the previous version.',
                '-n, --no-backup' => 'Run without checking locked versions or creating backup locks.',
                '-i, --invoke' => 'Execute the seeder and invoke additional invokable seeders.',
            ],
            examples: [
                'php novakit db:seed',
                'php novakit db:seed --class=TestSeeder',
                'php novakit db:seed --class=TestSeeder --rollback',
                'php novakit db:seed --class=TestSeeder --rollback --table=Foo',
                'php novakit db:seed --class=TestSeeder --no-backup',
                'php novakit db:seed --no-backup',
            ],
        );

        self::add(
            'db:migrate',
            'Database',
            'Execute database table migrations.',
            usages: [
                'php novakit db:migrate' => 'Execute all database migration classes.',
                'php novakit db:migrate --class=<class-name>' => 'Execute a specific migration class.',
            ],
            options: [
                '-c, --class' => 'Migration class to execute.',
                '-n, --no-backup' => 'Run without checking locked versions or creating backup locks.',
                '-d, --drop' => 'Drop the table when executing the down migration method.',
                '-r, --rollback' => 'Rollback the migration to the previous version.',
                '-b, --debug' => 'Print generated migration SQL without applying changes.',
                '-i, --invoke' => 'Execute the migration and invoke additional invokable migrations.',
            ],
            examples: [
                'php novakit db:migrate',
                'php novakit db:migrate --class=TestMigration',
                'php novakit db:migrate --class=TestMigration --rollback',
                'php novakit db:migrate --class=TestMigration --no-backup',
                'php novakit db:migrate --no-backup',
            ],
        );

        self::add(
            'cron:create',
            'CronWorker',
            'Create cron tasks and lock them in the cron lock file.',
            usages: [
                'php novakit cron:create',
            ],
            options: [
                '-f, --force' => 'Update locked tasks with changes from the cron class.',
            ],
            examples: [
                'php novakit cron:create',
                'php novakit cron:create --force',
            ],
        );

        self::add(
            'cron:run',
            'CronWorker',
            'Run cron jobs locked in the cron lock file.',
            usages: [
                'php novakit cron:run',
            ],
            options: [
                '-f, --force' => 'Update locked tasks with changes from the cron class.',
                '-s, --sleep' => 'Delay between task executions in microseconds (default: 100000).',
            ],
            examples: [
                'php novakit cron:run',
                'php novakit cron:run --force',
                'php novakit cron:run --sleep=100000',
            ],
        );

        self::add(
            'task:init',
            'TaskWorker',
            description: "Initialize the task queue system and create the required database table.",
            usages: [
                'php novakit task:init',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
            ],
            examples: [
                'php novakit task:init --class=App\\Tasks\\MyTask' =>
                    'Create the task queue table using a custom task class.',
            ],
        );

        self::add(
            'task:deinit',
            'TaskWorker',
            'Remove the task queue table and all associated tasks.',
            usages: [
                'php novakit task:deinit',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION
            ],
            examples: [
                'php novakit task:deinit --class=App\\Tasks\\MyTask' =>
                    'Drop the task table defined by a custom task class.',
            ],
        );

        self::add(
            'task:queue',
            'TaskWorker',
            'Add a task to the queue for later execution.',
            usages: [
                'php novakit task:queue -t=App\\Utils\\MyHandler@run -a=\'["param1", 2, true]\'',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
                '-t, --task' => 'Handler to execute: function, static method (Class::method), or instance method (Class@method). Not required when tasks are added through TaskQueue->tasks().',
                '-a, --args' => 'JSON array of arguments passed to the handler.',
                '-s, --schedule' => 'Execution delay. Accepts UNIX timestamp, date (Y-m-d H:i:s), or relative time (e.g., "+5 minutes").',
                '-p, --priority' => 'Task priority (0 = highest, 100 = lowest). Default: 0.',
                '-f, --forever' => 'Repeat interval in minutes (minimum: 5) for recurring tasks after completion or failure.',
                '-r, --retries' => 'Number of retry attempts after failure (default: 0, unlimited).',
            ],
            examples: [
                'php novakit task:queue -t=App\\Service@handle -a=\'["foo", 42]\''
                    => 'Queue a class method with arguments.',
            ],
        );

        self::add(
            'task:list',
            'TaskWorker',
            'List queued tasks with optional filters.',
            usages: [
                'php novakit task:list --status=pending --limit=10 --offset=0',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
                '-s, --status' => 'Filter tasks by status (e.g., pending, running, completed).',
                '-l, --limit' => 'Maximum number of tasks to display.',
                '-o, --offset' => 'Number of tasks to skip.',
            ],
            examples: [
                'php novakit task:list --status=pending --limit=5' =>
                    'List the first 5 pending tasks.',
            ],
        );

        self::add(
            'task:export',
            'TaskWorker',
            'Export queued tasks with optional status filtering.',
            usages: [
                'php novakit task:export --dir=path/to/export/tasks.php --status=pending',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
                '-d, --dir' => 'File path for exported tasks.',
                '-s, --status' => 'Filter tasks by status (default: all).',
            ],
            examples: [
                'php novakit task:export --dir=path/to/export/tasks.php' =>
                    'Export all tasks to the specified file.',
            ],
        );

        self::add(
            'task:info',
            'TaskWorker',
            'Display details for a specific task.',
            usages: [
                'php novakit task:info --id=42',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
                '-i, --id' => 'Task ID to inspect.'
            ],
            examples: [
                'php novakit task:info --id=99' =>
                    'Display details for task #99.',
            ],
        );

        self::add(
            'task:delete',
            'TaskWorker',
            'Delete a task from the queue.',
            usages: [
                'php novakit task:delete --id=42',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
                '-i, --id' => 'Task ID to delete.'
            ],
            examples: [
                'php novakit task:delete --id=15' =>
                    'Remove task #15 from the database.',
            ],
        );

        self::add(
            'task:purge',
            'TaskWorker',
            'Remove queued tasks by status.',
            usages: [
                'php novakit task:purge --status=completed',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
                '-s, --status' => 'Task status to remove (default: all).',
            ],
            examples: [
                'php novakit task:purge --status=failed' =>
                    'Remove all failed tasks.',
            ],
        );

        self::add(
            'task:pause',
            'TaskWorker',
            'Pause a running or pending task.',
            usages: [
                'php novakit task:pause --id=42',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
                '-i, --id' => 'Task ID to pause.',
                '-p, --priority' => 'Task priority (0 = highest, 100 = lowest).',
            ],
            examples: [
                'php novakit task:pause --id=7' =>
                    'Pause task #7 if supported.',
            ],
        );

        self::add(
            'task:resume',
            'TaskWorker',
            'Resume a paused task.',
            usages: [
                'php novakit task:resume --id=42',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
                '-i, --id' => 'ID of the paused task.',
            ],
            examples: [
                'php novakit task:resume --id=7' =>
                    'Resume task #7.',
            ],
        );

        self::add(
            'task:retry',
            'TaskWorker',
            'Retry a failed task by moving it back to pending status.',
            usages: [
                'php novakit task:retry --id=42',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
                '-i, --id' => 'ID of the failed task to retry.',
            ],
            examples: [
                'php novakit task:retry --id=9' =>
                    'Retry a failed task.',
            ],
        );

        self::add(
            'task:sig',
            'TaskWorker',
            'Send control signals to the task worker.',
            usages: [
                'php novakit task:sig --stop-worker',
                'php novakit task:sig --resume-worker',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
                '-s, --stop-worker' => 'Stop the worker by creating a signal lock file.',
                '-r, --resume-worker' => 'Resume the worker by removing the signal lock file.',
            ],
            examples: [
                'php novakit task:sig --stop-worker' =>
                    'Gracefully stop the running task worker.',
                'php novakit task:sig --resume-worker' =>
                    'Resume a stopped worker by removing the signal file.',
            ],
        );

        self::add(
            'task:status',
            'TaskWorker',
            'Update the status of a task.',
            usages: [
                'php novakit task:status --id=42 --status=completed',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
                '-i, --id' => 'Task ID to update.',
                '-s, --status' => 'New task status (e.g., pending, paused, running, completed).',
            ],
            examples: [
                'php novakit task:status --id=42 --status=paused' =>
                    'Set task #42 to paused status.',
            ],
        );

        self::add(
            'task:run',
            'TaskWorker',
            'Execute queued tasks in a worker loop with optional execution limits and idle timeout.',
            usages: [
                'php novakit task:run --limit=10 --sleep=500000 --idle=5',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
                '-o, --output' => 'Log output path or log level (e.g., debug).',
                '-s, --sleep' => 'Microseconds to wait between tasks (default: 100000).',
                '-l, --limit' => 'Maximum number of tasks to process.',
                '-i, --id' => 'Run a specific task by ID.',
                '-f, --flock-worker' => 'Use a file lock to prevent multiple workers running simultaneously.',
                '--idle' => 'Maximum idle attempts before stopping.',
            ],
            examples: [
                'php novakit task:run --output=debug --limit=5' =>
                    'Run up to 5 tasks with debug output.',
            ],
        );

        self::add(
            'task:listen',
            'TaskWorker',
            'Deprecated. Use task:tail instead.',
            usages: [
                'php novakit task:listen',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
            ],
        );

        self::add(
            'task:tail',
            'TaskWorker',
            description: "Monitor task queue logs and display new events in real-time.\n"
                . "Requires the task class '\$eventLogging' property to be enabled.",
            usages: [
                'php novakit task:tail',
            ],
            options: [
                '-c, --class' => self::TASK_Q_CLASS_DESCRIPTION,
                '-j, --json' => 'Output log lines as formatted JSON when possible.',
                '-s, --since' => 'Filter logs by time (e.g., 10s, 5m, 2h, or timestamp).',
                '-g, --grep' => 'Show only log lines containing the specified text.',
                '-x, --exclude' => 'Hide log lines containing the specified text.',
                '-w, --wait' => 'Wait time in seconds for tail operations.',
                '-r, --retry' => 'Retry attempts when the log file is unavailable.',
            ],
            examples: [
                'php novakit task:tail --class=App\\Tasks\\MyTask' =>
                    'Monitor events from a custom task class.',
                'php novakit task:tail --json --since=10m' =>
                    'Display the last 10 minutes of logs in JSON format.',
                'php novakit task:tail --grep=ERROR --exclude=DEBUG' =>
                    'Show ERROR logs while excluding DEBUG entries.',
            ],
        );

        self::add(
            'cache',
            'Cache',
            'Manage system caches by clearing, deleting, or listing cache items.',
            usages: [
                'php novakit cache:clear',
                'php novakit cache:clear --key=TestKey',
                'php novakit cache:list',
            ],
            options: [
                '--key' => 'Cache key to delete.',
                '--storage' => 'Cache storage name to delete.',
            ],
            examples: [
                'php novakit cache:clear',
                'php novakit cache:clear --key=TestKey',
                'php novakit cache:list',
            ],
        );

        self::add(
            'clear:caches',
            'Clear',
            description: "Clear cached pages, database cache files, and route files.\n"
                . 'Clears all cached files when no directory is specified.',
            usages: [
                'php novakit clear:caches',
            ],
            options: [
                '-d, --dir=<name>' => 'Cache subdirectory to clear (e.g., routes).',
            ],
            examples: [
                'php novakit clear:caches --dir=routes' =>
                    'Clear cached route files.',
            ],
        );

        self::add(
            'clear:routes',
            'Clear',
            'Clear cached route attribute files.',
            usages: [
                'php novakit clear:routes',
            ],
            examples: [
                'php novakit clear:routes' =>
                    'Clear cached route files.',
            ],
        );

        self::add(
            'clear:storage',
            'Clear',
            'Clear all files from the private storage directory.',
            usages: [
                'php novakit clear:storage',
            ],
            examples: [
                'php novakit clear:storage' =>
                    'Clear all private storage files.',
            ],
        );

        self::add(
            'clear:temp',
            'Clear',
            'Clear all files from the temporary directory.',
            usages: [
                'php novakit clear:temp',
            ],
            examples: [
                'php novakit clear:temp' =>
                    'Clear all temporary files.',
            ],
        );

        self::add(
            'clear:writable',
            'Clear',
            'Clear files and directories from the writable directory.',
            usages: [
                'php novakit clear:writable',
            ],
            options: [
                '-d, --dir=<name>' => 'Directory inside writable to clear.',
                '-p, --parent' => 'Remove the selected directory itself.',
            ],
            examples: [
                'php novakit clear:writable --dir=temp' =>
                    'Clear all files in writable/temp.',
                'php novakit clear:writable --dir=temp --parent' =>
                    'Clear writable/temp and remove the temp directory.',
            ],
        );
    }
}