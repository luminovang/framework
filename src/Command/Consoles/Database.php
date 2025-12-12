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

use \Throwable;
use \Luminova\Boot;
use \Luminova\Base\Console;
use \Luminova\Command\Terminal;
use \Luminova\Storage\Filesystem;
use \Luminova\Command\Utils\Color;
use \Luminova\Foundation\Module\Caller;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Database\{Seeder, Builder, Migration};
use function \Luminova\Funcs\{
    root,
    make_dir,
    get_content,
    write_content,
    get_class_name,
    display_path
};

class Database extends Console 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'db';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'Database';

    /**
     * {@inheritdoc}
     */
    protected array|string $usages = [
        'php novakit db:clear --help',
        'php novakit db:drop --help',
        'php novakit db:alter --help',
        'php novakit db:truncate --help',
        'php novakit db:seed --help',
        'php novakit db:migrate --help'
    ];

    /**
     * @var ?Builder $builder
     */
    private static ?Builder $builder = null;

    /**
     * @var bool $isDebug
     */
    private static bool $isDebug = false;

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        // Enable CLI exception handling temporarily
        setenv('throw.cli.exceptions', 'true');

        try {
            self::$builder = (new Builder())->connection();
        } catch (Throwable $e) {
            Terminal::error("Database Connection Error: " . $e->getMessage());
            return STATUS_ERROR;
        }

        self::$isDebug = $this->input->hasOption('debug', 'b');
        Boot::set(Boot::QUERY_DEBUG, self::$isDebug);

        $command = trim($this->input->getName());

        return match ($command) {
            'db:clear'     => $this->clearLocks(),
            'db:drop'      => $this->executeMigration(true),
            'db:alter'     => $this->alterTable(),
            'db:truncate'  => $this->doTruncate(),
            'db:seed'      => $this->executeSeeder(),
            'db:migrate'   => $this->executeMigration(),
            default        => STATUS_ERROR,
        };
    }

    /**
     * {@inheritdoc}
     */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    /**
     * Run database table truncation.
     * 
     * @param mixed $table The table to truncate.
     * 
     * @return int Returns STATUS_SUCCESS on successful execution, STATUS_ERROR on failure.
     */
    private function doTruncate(mixed $table = null): int 
    {
        $table ??= $this->input->getAnyOption('table', 't');

        if ($table === true || !$table) {
            Terminal::error(
                "Error: You must specify the table name using '--table=Foo'."
            );
            return STATUS_ERROR;
        }

        $tbl = self::$builder->from($table);
        $inTransaction = false;

        if (!$this->input->hasOption('no-transaction', 'n')) {
            $inTransaction = $tbl->nestedTransaction() !== false;
        }

        $success = $tbl->truncate();

        if ($success && $inTransaction) {
            $success = $tbl->commit();
        }

        if ($success) {
            Terminal::writeln(
                sprintf(
                    '[%s] Table was truncated successfully.', 
                    Color::style($table, 'green')
                )
            );
            return STATUS_SUCCESS;
        }

        if ($inTransaction) {
            $tbl->rollback();
        }

        Terminal::writeln(
            sprintf(
                '[%s] No records were truncated for table.', 
                Color::style($table, 'yellow')
            )
        );

        return STATUS_ERROR;
    }

    /**
     * Clear all migrations & seeders files and locks.
     * 
     * @return int Returns STATUS_SUCCESS on successful execution, STATUS_ERROR on failure.
     */
    private function clearLocks(): int 
    {
        $context = $this->input->getAnyOption('lock', 'l', null);
        $class = $this->input->getAnyOption('class', 'c');

        if ($class === true || !$class) {
            Terminal::error(
                "Class name required argument '--class=Foo'."
            );
            return STATUS_ERROR;
        }

        if ($context === true || !$context) {
            Terminal::error(
                "Lock argument required '--lock=seeder' to clear seeder lock files"
                . " or '--lock=migration' to clear migration lock files."
            );
            return STATUS_ERROR;
        }
        
        $deleted = 0;
        $backup = null;
        
        if ($context === 'seeder') {
            $backup = root('/writeable/database/Seeders/');
            $filename = $backup . 'seeders.lock';
        } elseif ($context === 'migration') {
            $backup = root('/writeable/database/Migrations/');
            $filename = $backup . 'migrations.lock';
        }
        
        if ($backup === null) {
            Terminal::error(
                "Error: Unsupported lock type '{$context}'." 
                . " Supported values 'seeder' or 'migration'."
            );
            return STATUS_ERROR;
        }
        
        if ($class === false || empty($class)) {
            Filesystem::delete($backup, false, $deleted);
            
            if ($deleted > 0) {
                Terminal::writeln(
                    sprintf(
                        '[%s] %d lock file%s was removed.', 
                        Color::style($context, 'green'),
                        $deleted,
                        ($deleted > 1) ? 's' : ''
                    )
                );
                return STATUS_SUCCESS;
            }

            Terminal::writeln(
                sprintf(
                    '[%s] No lock files to clear.', 
                    Color::style($context, 'red')
                )
            );

            return STATUS_ERROR;
        }

        $className = get_class_name($class);
        $lock = $this->getLock($filename);

        if ($lock === []) {
            return STATUS_ERROR;
        }

        $entry = $lock[$className] ?? [];
        $metadata = $entry['metadata'] ?? [];

        if (empty($entry) || empty($metadata)) {
            Terminal::writeln(
                sprintf(
                    '[%s] No %s locked version found.', 
                    Color::style($className, 'red'),
                    $context
                )
            );
            return STATUS_ERROR;
        }

        foreach ($metadata as $ver => $line) {
            if (is_file($backup . $line['backup'])) {
                if (unlink($backup . $line['backup'])) {
                    $deleted++;
                    unset($lock[$className]['metadata'][$ver]);
                }
            } else {
                $deleted++;
                unset($lock[$className]['metadata'][$ver]);
            }
        }

        if ($deleted > 0) {
            if (empty($lock[$className]['metadata'])) {
                unset($lock[$className]);
            } else {
                $last = array_last($metadata);
                $lock[$className]['latestVersion'] = $last['version'];
            }

            if (write_content($filename, json_encode($lock, JSON_PRETTY_PRINT))) {
                Terminal::writeln(
                    sprintf(
                        '[%s] %d %s lock file%s was removed.', 
                        Color::style($className, 'green'),
                        $deleted,
                        $context,
                        ($deleted > 1) ? 's' : ''
                    )
                );
                return STATUS_SUCCESS;
            }
        }
 
        Terminal::writeln(
            sprintf(
                '[%s] No %s lock files to clear.', 
                Color::style($className, 'red'),
                $context
            )
        );
        
        return STATUS_ERROR;
    }
   
    /**
     * Run database table migration alter.
     * 
     * @return int Returns STATUS_SUCCESS on successful execution, STATUS_ERROR on failure.
     */
    private function alterTable(): int 
    {
        $class = $this->input->getAnyOption('class', 'c', false);

        if ($class === true || !$class) {
            Terminal::error(
                "Altering table required argument '--class=Foo'. Empty values are not supported.",
            );
            return STATUS_ERROR;
        }

        $noBackup = $this->input->hasOption('no-backup', 'n');
        Boot::set(Boot::ALTER_DROP_COLUMNS, $this->input->hasOption('drop-columns', 'd'));
        Boot::set(Boot::CHECK_ALTER_TABLE, true);

        $lock = [];
        $backup = null;
        $executed = 0;
        $path = root('/app/Database/Migrations/');

        if (!$noBackup && !self::$isDebug) {
            $backup = root('/writeable/database/Migrations/');
            $filename = $backup . 'migrations.lock';
            $lock = $this->getLock($filename, false);
        }

        try {
            $className = get_class_name($class);
            $class = "\\App\\Database\\Migrations\\{$class}";
            /** @var Migration $instance */
            $instance = new $class();

            if($instance->up()){
                Terminal::writeln(sprintf(
                    '[%s] Database migration downgraded.', 
                    Color::style($className, 'green')
                ));

                usleep(100000);
            }

            if($instance->alter()){
                Terminal::writeln(sprintf(
                    '[%s] Database sheme altered.', 
                    Color::style($className, 'green')
                ));
            }

            if (self::$isDebug) {
                return STATUS_SUCCESS;
            }

            if (Boot::get(Boot::ALTER_SUCCESS) === true) {
                if (
                    !$noBackup && 
                    ($lock === [] || !$this->guardVersion($class, $lock, $path, $backup, null, true))
                ) {
                    self::lockFile($lock, $class, $path, $backup);
                }

                Terminal::writeln(
                    sprintf(
                        '[%s] Migration table altered.', 
                        Color::style($className, 'green')
                    )
                );
            }

            Terminal::newLine();

            if ($executed > 0) {
                Terminal::success('Database migration altered successfully.');
                return STATUS_SUCCESS;
            }

            Terminal::error('No migration tables were altered.');
        } catch (Throwable $e) {
            Terminal::newLine();
            Terminal::writeln(
                sprintf(
                    '[%s] Migration alter failed: %s.', 
                    Color::style($className, 'red'),
                    $e->getMessage()
                )
            );
        }

        return STATUS_ERROR;
    }

    /**
     * Executes database seeders based on command options.
     * 
     * @param string|null $class Optional class name.
     * 
     * @return int Returns STATUS_SUCCESS on successful execution, STATUS_ERROR on failure.
     */
    private function executeSeeder(
        ?string $class = null, 
        ?bool $noBackup = null, 
        ?bool $invokes = null,
        bool $normalizeClass = true,
        int &$executions = 0
    ): int
    {
        $class ??= $this->input->getAnyOption('class', 'c');

        if ($class === true || !$class) {
            Terminal::error(
                "Class name required argument '--class=Foo'."
            );
            return STATUS_ERROR;
        }

        $noBackup ??= $this->input->hasOption('no-backup', 'n');
        $invokes ??= $this->input->hasOption('invoke', 'i');

        if($this->input->hasOption('rollback', 'r')){
            return $this->rollbackSeeder($class, $noBackup, $invokes);
        }

        $lock = [];
        $backup = null; 
        $path = root('/app/Database/Seeders/');

        if(!$noBackup){
            $backup = root('/writeable/database/Seeders/');
            $filename = $backup . 'seeders.lock';

            $lock = $this->getLock($filename, false);
        }

        $className = get_class_name($class);
        /** @var class-string<Seeder>[] $classes */
        $classes = [$class];

        if($normalizeClass){
            $namespace = '\\App\\Database\\Seeders';
            $classes = $class 
                ? ["{$namespace}\\{$class}"]
                : Caller::extenders(Seeder::class, $path, $namespace);
        }

        $inTransaction = self::$builder->inTransaction();

        if(!$inTransaction){
            $inTransaction = self::$builder->nestedTransaction() !== false;
        }

        try {
            foreach ($classes as $seed) {
                if($lock !== [] && $this->guardVersion($seed, $lock, $path, $backup)){
                    continue;
                }

                /** @var Seeder $seeder */
                $seeder = new $seed();
                $executed = $this->doSeeding($seeder);

                if($inTransaction){
                    $executed ? self::$builder->commit() : self::$builder->rollback();
                }

                if(!$executed){
                    Terminal::writeln(
                        sprintf(
                            '[%s] No seeder was execution.', 
                            Color::style($className, 'red')
                        )
                    );
                    continue;
                }

                $executions++;

                if(!$noBackup){
                    self::lockFile($lock, $seed, $path, $backup, true);
                }

                Terminal::writeln(
                    sprintf(
                        '[%s] seeder was executed successfully.', 
                        Color::style($className, 'green')
                    )
                );

                if($invokes === true){
                    foreach ($seeder->getInvokes() as $subSeed) {
                        $this->executeSeeder(
                            $subSeed, 
                            $noBackup, 
                            $invokes, 
                            false, 
                            $executions
                        );

                        usleep(100000);
                    }
                }
            }

            Terminal::newLine();

            if($executions > 0){
                Terminal::success(
                    sprintf(
                        '%d seeder%s was executed successfully.', 
                        $executions,
                        ($executions > 1) ? 's' : ''
                    )
                );

                return STATUS_SUCCESS;
            }

            Terminal::error('No record was seeded.');
        } catch (Throwable $e) {
            if($inTransaction){
                self::$builder->rollback();
            }

            Terminal::newLine();
            Terminal::writeln(
                sprintf(
                    '[%s] Seeder execution failed: %s', 
                    Color::style($className, 'red'),
                    $e->getMessage()
                )
            );
        }

        return STATUS_ERROR;
    }

    /**
     * Executes database migrations or rolls them back based on command options.
     *
     * @param bool|null $drop Run migrations drop table.
     * 
     * @return int Returns STATUS_SUCCESS on successful execution, STATUS_ERROR on failure.
     */
    private function executeMigration(
        ?bool $drop = null, 
        ?string $class = null,
        ?bool $noBackup = null,
        ?bool $invokes = null,
        ?bool $isRollback = null,
        bool $normalizeClass = true,
        int &$executed = 0
    ): int 
    {
        $class ??= $this->input->getAnyOption('class', 'c');
 
        if ($class === true || !$class) {
            Terminal::error(
                "Class name required argument '--class=Foo'."
            );
            return STATUS_ERROR;
        }

        $noBackup ??= $this->input->hasOption('no-backup', 'n');
        $invokes ??= $this->input->hasOption('invoke', 'i');
        $isRollback ??= $this->input->hasOption('rollback', 'r'); 

        if(!$drop && $isRollback){
            self::$isDebug = false;
            return $this->rollbackMigration($class, $noBackup, $invokes);
        }

        $lock = [];
        $backup = null; 
        $executed = 0; 
        $drop ??= $this->input->hasOption('drop', 'd');
        self::$isDebug = $drop ? false : self::$isDebug;
        $path = root('/app/Database/Migrations/');
        $shouldGuard = (self::$isDebug === false && $drop === false);

        if(!self::$isDebug && $noBackup === false){
            $backup = root('/writeable/database/Migrations/');
            $filename = $backup . 'migrations.lock';

            $lock = $this->getLock($filename, false);
        }

        $className = get_class_name($class);
        $classes = [$class];

        if($normalizeClass){
            $namespace = '\\App\\Database\\Migrations';
            $classes = $class 
                ? ["{$namespace}\\{$class}"] 
                : Caller::extenders(Migration::class, $path, $namespace);
        }

        try {
            foreach ($classes as $migrate) {
                $migrants = [];

                if($shouldGuard && $lock !== [] && $this->guardVersion($migrate, $lock, $path, $backup)){
                    continue;
                }

                if(!$this->doMigration($migrate, $drop, $invokes, $migrants)){
                    continue;
                }

                $executed++;

                if($noBackup === false){
                    self::lockFile($lock, $migrate, $path, $backup, false, $drop);
                }

                if($invokes){
                    foreach ($migrants as $migrate) {
                        $this->executeMigration(
                            $drop, 
                            $migrate, 
                            $noBackup, 
                            $invokes, 
                            $isRollback,
                            false,
                            $executed
                        );
                    }
                }

                usleep(100000);
            }

            if(self::$isDebug){
                return STATUS_SUCCESS;
            }

            Terminal::newLine();

            if($executed > 0){
                Terminal::success(
                    sprintf(
                        '%d migration%s was %s successfully.',
                        $executed,
                        ($executed > 1) ? 's' : '',
                        $drop ? 'downgraded' : 'upgraded'
                    )
                );

                return STATUS_SUCCESS;
            }

            Terminal::error('No database migration executed.');
        } catch (Throwable $e) {
            $db = Boot::get(Boot::DROP_TRANSACTION);

            if($db instanceof DatabaseInterface && $db->inTransaction()){
                $db->rollback();
            }

            Terminal::newLine();
            Terminal::writeln(
                    sprintf(
                    '[%s] Migration execution failed: %s', 
                    Color::style($className, 'red'),
                    $e->getMessage()
                )
            );
        }

        return STATUS_ERROR;
    }

    /**
     * Executes migrations.
     *
     * @param string $namespace The migrations class namespace.
     * @param bool $drop Whether to migrations drop table.
     * @param bool $invokes Whether to invoke migrations classes.
     * @param array $migrants Pass migrations invokers by reference.
     * 
     * @return bool Returns true if migrations otherwise false.
     */
    private function doMigration(
        string $namespace, 
        bool $drop = false, 
        bool $invokes = false,
        array &$migrants = []
    ): bool 
    {
        /** @var Migration $instance */
        $instance = new $namespace();
        $className = get_class_name($namespace);

        try {
            if(!self::$isDebug && $instance->down()){
                Terminal::writeln(sprintf(
                    '[%s] Database migration downgraded.', 
                    Color::style($className, 'green')
                ));

                if(!$drop){
                    usleep(100000);
                }
            }

            if (self::$isDebug || !$drop) {
                if($instance->up()){
                    Terminal::writeln(sprintf(
                        '[%s] Database migration upgraded%s.', 
                        Color::style($className, 'green'),
                        self::$isDebug ? ' in debug mode, without affecting changes' : ''
                    ));
                }

                if (self::$isDebug) {
                    return false;
                }
            }

            if ($invokes === true ) {
                $migrants = $instance->getInvokes();
            }

            /** @var DatabaseInterface $db */
            $db = Boot::get(Boot::DROP_TRANSACTION);
            $inTransaction = ($db instanceof DatabaseInterface && $db->inTransaction());

            if (Boot::get(Boot::MIGRATION_SUCCESS) === true) {
                if ($inTransaction) {
                    return $db->commit();
                }

                Terminal::writeln(
                        sprintf(
                        '[%s] Migration applied successfully.', 
                        Color::style($className, 'green')
                    )
                );
                return true;
            }

            if ($inTransaction) {
                $db->rollback();
            }

            Terminal::writeln(
                    sprintf(
                    '[%s] Migration failed (no changes applied)', 
                    Color::style($className, 'yellow')
                )
            );
        } catch (Throwable $e) {
            $db = Boot::get(Boot::DROP_TRANSACTION);
            if ($db instanceof DatabaseInterface && $db->inTransaction()) {
                $db->rollback();
            }
            Terminal::writeln(
                    sprintf(
                    '[%s] Migration failed: %s', 
                    Color::style($className, 'red'),
                    $e->getMessage()
                )
            );
        }

        return false;
    }

    /**
     * Execute seeder.
     * 
     * @param Seeder $seeder The seeder to execute.
     * 
     * @return bool Return true if seeder succeeded, false otherwise.
     */
    private function doSeeding(Seeder $seeder): bool
    {
        $className = get_class_name($seeder);

        try {
            $count = (int) $seeder->run(self::$builder);

            if ($count > 0) {
                Terminal::writeln(sprintf(
                    '[%s] Seeded %d record%s.', 
                    Color::style($className, 'green'), 
                    $count, 
                    ($count === 1) ? '' : 's'
                ));
                return true;
            }

            Terminal::writeln(
                sprintf('[%s] No records seeded.', Color::style($className, 'yellow')),
            );

        } catch (Throwable $e) {
            Terminal::writeln(
                sprintf('[%s] Seeding failed: %s', Color::style($className, 'red'), $e->getMessage())
            );
        }

        return false;
    }

    /**
     * Rolls back a migration to a specified version or the last one executed.
     *
     * @param mixed $class The class name of the migration to rollback.
     * @param bool $noBackup Whether no backup flag is passed.
     * @param bool $noInvokes Whether to ignore invoking invokers.
     * @param int|string|null $input Pass input version number to invoke invokers.
     *
     * @return int Returns STATUS_SUCCESS on successful rollback, STATUS_ERROR on failure.
     */
    private function rollbackMigration(
        mixed $class, 
        bool $noBackup = false, 
        bool $invokes = false, 
        string|int|null $input = null,
        bool $normalizeClass = true,
        int &$executions = 0
    ): int 
    {
        if ($class === false || !$class) {
            Terminal::error(
                'Migration class is required (use --class=Foo --rollback).'
            );
            return STATUS_ERROR;
        }

        $filepath = root('/writeable/database/Migrations/');
        $filename =  "{$filepath}migrations.lock";
        $className = get_class_name($class);
  
        if($normalizeClass){
            $class = "\\App\\Database\\Migrations\\{$class}";
        }

        $lock = $this->getLock($filename);

        if ($lock === []) {
            Terminal::writeln(
                sprintf('[%s] Nothing to rollback.', Color::style($className, 'yellow'))
            );
            return STATUS_ERROR;
        }

        if (!isset($lock[$className])) {
            Terminal::writeln(
                sprintf('[%s] No lock found.', Color::style($className, 'red'))
            );
            return STATUS_ERROR;
        }

        $metadata = $lock[$className]['metadata'] ?? [];

        if ($metadata === []) {
            Terminal::writeln(
                sprintf('[%s] No backup metadata found.', Color::style($className, 'red'))
            );
            return STATUS_ERROR;
        }

        $versions = $this->listLocks($lock, $class, $className);

        $input ??= Terminal::prompt(
            'Enter version to rollback to:',
            $versions,
            'required|in_array([' . implode(',', $versions) . '], false)'
        );

        if ($input === $lock[$className]['latestVersion']) {
            Terminal::writeln(
                sprintf('[%s] Already at version "%s".', Color::style($className, 'yellow'),  $input)
            );
            return STATUS_ERROR;
        }

        if (!in_array($input, $versions)) {
            Terminal::writeln(
                sprintf('[%s] Version "%s" does not exist.', Color::style($className, 'red'),  $input)
            );
            return STATUS_ERROR;
        }

        try {
            $backupFile = $filepath . $metadata[$input]['backup'];
            $migrationPath = root('/app/Database/Migrations/');

            if ($this->guardVersion($class, $lock, $migrationPath, $filepath, (int) $input)) {
                return STATUS_SUCCESS;
            }

            $newClass = "{$migrationPath}{$className}.php";

            if (copy($backupFile, $newClass)) {
                $migrants = [];

                if ($this->doMigration($class, false, $invokes, $migrants)) {
                    $executions++;

                    if (!$noBackup) {
                        self::updateLockFile(
                            $lock,
                            (int) $input,
                            $class,
                            $class,
                            $migrationPath,
                            $filepath
                        );
                    }

                    foreach ($migrants as $migrate) {
                        if ($migrate === '') {
                            continue;
                        }

                        $status = $this->rollbackMigration(
                            $migrate, 
                            $noBackup, 
                            true, 
                            $input, 
                            false,
                            $executions
                        );

                        if ($status === STATUS_SUCCESS) {
                            $executions++;
                            usleep(100000);
                        }
                    }
                }
            }

            Terminal::newLine();
            if ($executions > 0) {
                Terminal::success(sprintf(
                    '%d Migration%s rolled back to version "%s".', 
                    $executions,
                    ($executions > 1) ? 's' : '',
                    $input
                ));
                return STATUS_SUCCESS;
            }

            Terminal::error(sprintf(
                'No migrations rolled back for version "%s".',  
                $input
            ));
        } catch (Throwable $e) {
            $db = Boot::get(Boot::DROP_TRANSACTION);

            if ($db instanceof DatabaseInterface && $db->inTransaction()) {
                $db->rollback();
            }

            Terminal::newLine();
            Terminal::writeln(
                sprintf('[%s] Rollback failed %s', Color::style($className, 'red'), $e->getMessage())
            );
        }

        return STATUS_ERROR;
    }

    /**
     * Rolls back a seeder table to a specified version or the last one executed.
     *
     * @param mixed $class The class name of the seeder to rollback.
     * @param bool $noBackup Whether no backup flag is passed.
     * @param bool $invokes Whether to invokes other invocable seeders (default: false).
     *
     * @return int Returns STATUS_SUCCESS on successful rollback, STATUS_ERROR on failure.
     */
    private function rollbackSeeder(
        mixed $class, 
        bool $noBackup = false, 
        bool $invokes = false,
        string|int|null $input = null,
        bool $normalizeClass = true,
        int &$executions = 0
    ): int 
    {
        if ($class === true || !$class) {
            Terminal::error(
                'Seeder class is required (use --class=Foo --rollback).'
            );
            return STATUS_ERROR;
        }

        $filepath = root('/writeable/database/Seeders/');
        $filename = "{$filepath}seeders.lock";
        $className = get_class_name($class);
 
        if($normalizeClass){
            $class = "\\App\\Database\\Seeders\\{$class}";
        }

        $lock = $this->getLock($filename);

        if ($lock === [] || !isset($lock[$className])) {
            Terminal::writeln(
                sprintf('[%s] Nothing to rollback.', Color::style($className, 'red'))
            );
            return STATUS_ERROR;
        }

        Boot::set(Boot::QUERY_DEBUG, $this->input->hasOption('debug', 'b'));
        $table = $this->input->getAnyOption('table', 't', false);
        $isNoTable = (!$table || $table === true);

        $truncated = false;
        $metadata = $lock[$className]['metadata'] ?? [];
        $versions = $this->listLocks($lock, $class, $className, 'Seed', $isNoTable);

        $input ??= Terminal::prompt(
            'Enter version to rollback to:',
            $versions,
            'required|in_array([' . implode(',', $versions) . '], false)'
        );

        if (!in_array($input, $versions)) {
            Terminal::writeln(
                sprintf('[%s] Version "%s" does not exist.', Color::style($className, 'red'), $input)
            );
            return STATUS_ERROR;
        }

        $inTransaction = self::$builder->inTransaction();

        if(!$inTransaction){
            $inTransaction = self::$builder->nestedTransaction() !== false;
        }

        try {
            $backupFile = $filepath . $metadata[$input]['backup'];
            $path = root('/app/Database/Seeders/');
            $continue = 'yes';

            if ($this->guardVersion($class, $lock, $path, $filepath, (int) $input)) {
                if($inTransaction){
                    self::$builder->rollback();
                }

                return STATUS_SUCCESS;
            }


            if (!$isNoTable) {
                if (self::$builder->from($table)->temp()) {
                    $truncated = $this->doTruncate($table) === STATUS_SUCCESS;
                } else {
                    Terminal::writeln(
                        sprintf(
                            '[%s] Temp table for "%s" could not be created. Recovery may fail.', 
                            Color::style($className, 'cyan'), 
                            $table
                        )
                    );

                    $continue = Terminal::prompt(
                        'Continue anyway?',
                        ['yes' => 'green', 'no' => 'red'],
                        'required|in_array([yes,no], true)'
                    );

                    if ($continue === 'yes') {
                        $this->doTruncate($table);
                    }
                }
            }

            $restoreClass = "{$path}{$className}.php";

            if ($continue === 'yes' && copy($backupFile, $restoreClass)) {

                $seeder = new $class();
                $executed = $this->doSeeding($seeder);

                if($inTransaction){
                    $executed ? self::$builder->commit() : self::$builder->rollback();
                }
         
                if ($executed) {
                    $executions++;

                    if (!$noBackup) {
                        self::updateLockFile($lock, (int) $input, null, $class, $path, $filepath, true);
                    }

                    Terminal::writeln(
                        sprintf(
                            '[%s] Rolled back to version "%s".', 
                            Color::style($className, 'cyan'), 
                            $input
                        )
                    );

                    if ($invokes === true) {
                        self::$builder->savepoint('seed_batch');

                        foreach ($seeder->getInvokes() as $seed) {
                            $this->rollbackSeeder($seed, $noBackup, $invokes, $input, false, $executions);
                            usleep(100000);
                        }
                    }
                }
            }

            if($truncated){
                if (Builder::exec("INSERT INTO {$table} SELECT * FROM temp_{$table}") > 0) {
                    Terminal::writeln(
                        sprintf(
                            '[%s] Table "%s" restored from backup', 
                            Color::style($className, 'green'), 
                            $table
                        )
                    );
                } else {
                    Terminal::writeln(
                        sprintf(
                            '[%s] No records restored for "%s"', 
                            Color::style($className, 'yellow'), 
                            $table
                        )
                    );
                }
            }

            Terminal::newLine();
            if ($executions > 0) {
                Terminal::success(sprintf(
                    '%d Seeder%s rolled back to version "%s".', 
                    $executions,
                    ($executions > 1) ? 's' : '',
                    $input
                ));
                return STATUS_SUCCESS;
            }

            Terminal::error(sprintf(
                'No seeders rolled back for version "%s".',  
                $input
            ));
        } catch (Throwable $e) {
            if($inTransaction){
                self::$builder->rollback();
            }

            Terminal::newLine();
            Terminal::writeln(
                sprintf(
                    '[%s] Rollback failed: %s', 
                    Color::style($className, 'red'), 
                    $e->getMessage()
                ),
            );
        }

        return STATUS_ERROR;
    }

    /**
     * Undocumented function
     *
     * @param string $filename
     * 
     * @return array
     */
    private function getLock(string $filename, bool $showError = true): array
    {
        $pathname = display_path($filename);

        if (!is_file($filename)) {
            if($showError){
                Terminal::writeln(
                    sprintf('Lock file not found: "%s".', $pathname),
                    'white',
                    'red'
                );
            }
            return [];
        }

        if (!is_readable($filename)) {
            if($showError){
                Terminal::writeln(
                    sprintf('Lock file is not readable: "%s".', $pathname),
                    'white',
                    'red'
                );
            }
            return [];
        }

        try {
            $content = get_content($filename);
        } catch (Throwable $e) {
            if($showError){
                Terminal::writeln(
                    sprintf('Failed to read lock file "%s": %s', $pathname, $e->getMessage()),
                    'white',
                    'red'
                );
            }
            return [];
        }

        if ($content === '' || $content === false) {
            if($showError){
                Terminal::writeln(
                    sprintf('No lock version found in: "%s".', $pathname),
                    'white',
                    'red'
                );
            }
            return [];
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            if($showError){
                Terminal::writeln(
                    sprintf('Invalid lock file format (JSON expected): "%s".', $pathname),
                    'white',
                    'red'
                );
            }

            return [];
        }

        return $data;
    }

    /**
     * List all available lock versions that can be rolled back to if available.
     * 
     * 
     * @param array $lock  The lock array where metadata will be stored.
     * @param string $class  The fully-qaulified class name.
     * @param string $className  The class base name.
     * @param string $title  The context title for table identifier.
     * 
     * @return array<int,array> Return list of all available versions number.
     */
    private function listLocks(
        array $lock, 
        string $class, 
        string $className,
        string $title = 'Migration',
        bool $warn = false
    ): array
    {
        $headers = ['Version', 'Backup', $title . ' Date'];
        $versions = [];
        $rows = [];
        $metadata = $lock[$className]['metadata'] ?? [];

        Terminal::writeln(sprintf("%s Class: %s", $title, $lock[$className]['namespace'] ?? $class));

        if ($warn) {
            Terminal::info(
                "Note:\nTo prevent adding new seed records instead of replacing them, " .
                "truncate the seeder table before rolling back.\n" .
                "Alternatively, use the `--table` option with your seed table name to truncate before rollback."
            );
        }

        foreach ($metadata as $item) {
            $version = $item['version'] ?? '';

            $versions[] = $version;
            $rows[] = [
                'Version' => $version,
                'Backup' => $item['backup'] ?? '',
                "{$title} Date" => $item['timestamp'] ?? ''
            ];
        }

        if ($rows !== []) {
            Terminal::print(Terminal::table($headers, $rows, null, 'green'));
        } else {
            Terminal::writeln(
                sprintf(
                    '[%s] No backup versions found for %s', 
                    Color::style($className, 'yellow'), 
                    $title
                )
            );
        }

        return $versions;
    }

    /**
     * Write migration or seeder metadata to a lock file and create a backup copy.
     *
     * @param array    $lock     The lock array where metadata will be stored.
     * @param string   $namespace  The namespace or class name of the migration or seeder.
     * @param string   $path     The path to the original migration or seeder file.
     * @param string   $backup   The directory path where backup files will be stored.
     * @param bool     $seeder   Whether the context refers to a seeder (`true`) or migration (`false`).
     * @param bool     $drop   Whether running drop migration.
     *
     * @return bool Returns true if the operation was successful, false otherwise.
     */
    private static function lockFile(
        array &$lock, 
        string $namespace, 
        string $path, 
        string $backup,
        bool $seeder = false,
        bool $drop = false
    ): bool 
    {
        $className = get_class_name($namespace);
        $entry = $lock[$className] ?? [];
        if($drop){
            if($lock === [] || $entry === []){
                return true;
            }

            $last = $entry['metadata'][$entry['latestVersion']] ?? [];
            if($last !== [] && unlink($backup . $last['backup'])){
                unset($lock[$className]['metadata'][$last['version']]);
            }
        }else{
            $timestamp = date('Y-m-d-H:i:s'); 
            $backupName = str_replace(':', '', $timestamp) . $className . '.php';
            $version = ($entry === []) ? 1 : ($entry['latestVersion'] + 1);

            $lock[$className]['namespace'] = $namespace;
            $lock[$className]['lastVersion'] = 0;
            $lock[$className]['latestVersion'] = $version;
            $lock[$className]['metadata'][$version] = [
                'backup' => $backupName,
                'timestamp' => $timestamp,
                'version' => $version
            ];
        }
    
        if (!make_dir($backup)) {
            return false;
        }
    
        if (!$drop && !copy($path . $className . '.php', $backup . $backupName)) {
            return false;
        }
    
        $filename = $seeder ? 'seeders.lock' : 'migrations.lock';
        return write_content($backup . $filename, json_encode($lock, JSON_PRETTY_PRINT));
    }

    /**
     * Write migration or seeder metadata to a lock file and create a backup copy.
     *
     * @param array    $lock     The lock array where metadata will be stored.
     * @param int      $version  The last version of migration or seeder.
     * @param string|null   $class  The class basename of migration or seeder.
     * @param string   $namespace  The namespace or class name of the migration or seeder.
     * @param string   $path     The path to the original migration or seeder file.
     * @param string   $backup   The directory path where backup files will be stored.
     * @param bool     $seeder   Whether the context refers to a seeder (`true`) or migration (`false`).
     *
     * @return bool Returns true if the operation was successful, false otherwise.
     */

    private static function updateLockFile(
        array $lock, 
        int $version,
        ?string $class, 
        string $namespace, 
        string $path, 
        string $backup,
        bool $seeder = false
    ): bool 
    {
        $className = $class ?? get_class_name($namespace);

        if (!make_dir($backup)) {
            Terminal::writeln(
                sprintf(
                    '[%s] Unable to create backup directory: %s', 
                    Color::style($className, 'red'), 
                    display_path($backup)
                )
            );
            return false;
        }

        if (!isset($lock[$className]['metadata'][$version])) {
            Terminal::writeln(
                sprintf(
                    '[%s] Lock metadata version %d not found.', 
                    Color::style($className, 'red'), 
                    $version
                ),
            );
            return false;
        }

        $entry = $lock[$className];
        $metadata = $entry['metadata'][$version];

        $timestamp = date('Y-m-d-H:i:s');
        $backupName = str_replace(':', '', $timestamp) . $className . '.php';
        $oldBackup = $backup . $metadata['backup'];
        $newVersion = ($entry['latestVersion'] ?? $version) + 1;

        $lock[$className]['namespace'] = $namespace;
        $lock[$className]['lastVersion'] = $version;
        $lock[$className]['latestVersion'] = $newVersion;
        $lock[$className]['metadata'][$newVersion] = [
            'backup' => $backupName,
            'timestamp' => $timestamp,
            'version' => $newVersion
        ];

        $sourceFile = $path . $className . '.php';
        $targetFile = $backup . $backupName;

        if (!copy($sourceFile, $targetFile)) {
            Terminal::writeln(
                sprintf(
                    '[%s] Failed to backup "%s" to "%s".', 
                    Color::style($className, 'red'), 
                    display_path($sourceFile), 
                    display_path($targetFile)
                )
            );
            return false;
        }

        if (is_file($oldBackup) && unlink($oldBackup)) {
            unset($lock[$className]['metadata'][$version]);
        }

        $lockFilename = $seeder ? 'seeders.lock' : 'migrations.lock';
        $lockPath = $backup . $lockFilename;

        $written = write_content($lockPath, json_encode($lock, JSON_PRETTY_PRINT));

        if (!$written) {
            Terminal::writeln(
                sprintf(
                    '[%s] Failed to write lock file %s".', 
                    Color::style($className, 'red'), 
                    display_path($lockPath)
                )
            );
        }

        return $written;
    }

    /**
     * Compare two files to see if any changes in the hash
     * 
     * @param string $source File source.
     * @param string $destination. File destination path.
     * 
     * @return bool  
     */
    private static function versionChanged(string $source, string $destination): bool
    {
        if (!is_file($source) || !is_file($destination)) {
            return true;
        }

        return md5_file($source) !== md5_file($destination);
    }

    /**
     * Guard migration and seeding to ensure only run once.
     *
     * @param string   $namespace  The namespace or class name of the migration or seeder.
     * @param array    $lock     The lock array where metadata will be stored.
     * @param string   $path     The path to the original migration or seeder file.
     * @param string   $backup   The directory path where backup files will be stored.
     * @param int|null $version  The version of migration or seeder to execute.
     * @param bool $alter Whether you are executing table alter.
     *
     * @return bool Returns true if the current version file is still same as last, false otherwise.
     */
    private function guardVersion(
        string $namespace, 
        array $lock, 
        string $path, 
        string $backup, 
        ?int $version = null,
        bool $alter = false
    ): bool 
    {
        $className = get_class_name($namespace);
        $entry = $lock[$className] ?? [];

        if($entry === [] || empty($entry['metadata'])){
            return false;
        }

        $metadata = $entry['metadata'];
        $last = ($version === null) 
            ? ($metadata[$entry['latestVersion']] ?? null) 
            : $metadata[$version] ?? null;

        if($last === null){
            return false;
        }

        if(!self::versionChanged($path . $className . '.php', $backup . $last['backup'])){
            if(!$alter){
                Terminal::writeln(
                    sprintf(
                        '[%s] Skipped (no changes detected).', 
                        Color::style($className, 'red')
                    )
                );
            }

            return true;
        }

        return false;
    }
}