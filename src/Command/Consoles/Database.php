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

use \Luminova\Interface\DatabaseInterface;
use \Luminova\Base\BaseConsole;
use \Luminova\Database\Builder;
use \Luminova\Command\Utils\Color;
use \Luminova\Database\Migration;
use \Luminova\Database\Seeder;
use \Luminova\Storages\FileManager;
use \Luminova\Application\Caller;
use \Luminova\Exceptions\AppException;
use \Exception;

class Database extends BaseConsole 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'Database';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'db';

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
        $this->term->perse($options);
        // Temporarily enable cli exception
        setenv('throw.cli.exceptions', 'true');
        try{
            self::$builder ??= Builder::getInstance();
        }catch(AppException|Exception $e){
            $this->writeln("Database Connection Error: " . $e->getMessage(), 'white', 'red');
            return STATUS_ERROR;
        }

        self::$isDebug = (bool) $this->term->getAnyOption('debug', 'b', false);
        shared('SHOW_QUERY_DEBUG', self::$isDebug);

        return match(trim($this->term->getCommand())){
            'db:clear' => $this->clearLocks(),
            'db:drop' => $this->executeMigration(true),
            'db:alter' => $this->alterTable(),
            'db:truncate' => $this->doTruncate(),
            'db:seed' => $this->executeSeeder(),
            'db:migrate' => $this->executeMigration(),
            default => STATUS_ERROR
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
        $table ??= $this->term->getAnyOption('table', 't');

        if ($table === false || $table === '') {
            $this->term->writeln("Error: You must specify the table name using '--table=Foo'.", 'white', 'red');
            return STATUS_ERROR;
        }
      
        $noTransaction = $this->term->getAnyOption('no-transaction', 'n', false);
 
        if(self::$builder->table($table)->truncate(!$noTransaction)){
            $this->term->writeln("Success: Table '{$table}' was truncated successfully.", 'white', 'green');
            return STATUS_SUCCESS;
        }

        $this->term->writeln("Failed: No records were truncated for table '{$table}'.", 'yellow');
        return STATUS_ERROR;
    }

    /**
     * Clear all migrations & seeders files and locks.
     * 
     * @return int Returns STATUS_SUCCESS on successful execution, STATUS_ERROR on failure.
     */
    private function clearLocks(): int 
    {
        $context = $this->term->getAnyOption('lock', 'l', null);
        $class = $this->term->getAnyOption('class', 'c');
        
        if ($context === true || $context === null) {
            $this->term->writeln("Error: Specify '--lock=seeder' to clear seeder lock files or '--lock=migration' to clear migration lock files.", 'white', 'red');
            return STATUS_ERROR;
        }

        if ($class === true) {
            $this->term->writeln("Error: Please specify a non-empty value for '--class=Foo'.", 'white', 'red');
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
            $this->term->writeln("Error: Unsupported lock context value '{$context}'. Allowed values are 'seeder' or 'migration'.", 'white', 'red');
            return STATUS_ERROR;
        }
        
        if ($class === false || empty($class)) {
            FileManager::remove($backup, false, $deleted);
            
            if ($deleted > 0) {
                $this->term->writeln("Success: '{$deleted}' {$context} lock file(s) deleted.", 'white', 'green');
                return STATUS_SUCCESS;
            }
        } else {
            $lock = [];
        
            if (file_exists($filename)) {
                $lock = get_content($filename);
                $lock = ($lock !== false && $lock !== '') ? json_decode($lock, true) : [];
            }
            
            if (empty($lock)) {
                $this->term->writeln("Error: No {$context} locked versions found in lock file.", 'white', 'red');
                return STATUS_ERROR;
            }

            $entry = $lock[$class] ?? [];
            $metadata = $entry['metadata'] ?? [];

            if (empty($entry) || empty($metadata)) {
                $this->term->writeln("Error: No {$context} locked version found for '{$class}'.", 'white', 'red');
                return STATUS_ERROR;
            }

            foreach ($metadata as $ver => $line) {
                if (file_exists($backup . $line['backup'])) {
                    if (unlink($backup . $line['backup'])) {
                        $deleted++;
                        unset($lock[$class]['metadata'][$ver]);
                    }
                } else {
                    $deleted++;
                    unset($lock[$class]['metadata'][$ver]);
                }
            }

            if ($deleted > 0) {
                if (empty($lock[$class]['metadata'])) {
                    unset($lock[$class]);
                } else {
                    $last = end($metadata);
                    $lock[$class]['latestVersion'] = $last['version'];
                }

                if (write_content($filename, json_encode($lock, JSON_PRETTY_PRINT))) {
                    $this->term->writeln("Success: '{$deleted}' {$context} lock file(s) for '{$class}' deleted.", 'white', 'green');
                    return STATUS_SUCCESS;
                }
            }
        }
        
        $this->term->writeln("Error: No {$context} lock files to clear.", 'white', 'red');
        return STATUS_ERROR;
    }
   
    /**
     * Run database table migration alter.
     * 
     * @return int Returns STATUS_SUCCESS on successful execution, STATUS_ERROR on failure.
     */
    private function alterTable(): int 
    {
        $class = $this->term->getAnyOption('class', 'c');

        if($class === true || empty($class)){
            $this->term->writeln("Error: Alter required argument '--class=Foo' with migration class name, and does not support empty value.", 'white', 'red');
            return STATUS_ERROR;
        }

        $noBackup = (bool) $this->term->getAnyOption('no-backup', 'n', false);
        shared('ALTER_DROP_COLUMNS', (bool) $this->term->getAnyOption('drop-columns', 'd', false));
        shared('CHECK_ALTER_TABLE', true);

        $lock = [];
        $backup = null; 
        $executed = 0; 
        $path = root('/app/Database/Migrations/');

        if(!$noBackup && !self::$isDebug){
            $backup = root('/writeable/database/Migrations/');
            if(file_exists($lockfile = $backup . 'migrations.lock')){
                $lock = get_content($lockfile);
                $lock = ($lock !== false && $lock !== '') ? json_decode($lock, true) : [];
            }
        }

        try {
            $migrateClass = "\\App\\Database\\Migrations\\{$class}";
            /**
             * @var Migration $instance
             */
            $instance = new $migrateClass();
            $instance->up();
            sleep(1);
            $instance->alter();

            if(self::$isDebug){
                return STATUS_SUCCESS;
            }

            if(shared('ALTER_SUCCESS') === true){
                $executed++;

                if(!$noBackup && ($lock === [] || !$this->guardVersion($migrateClass, $lock, $path, $backup, null, true))){
                    self::lockFile($lock, $migrateClass, $path, $backup);
                }
            }

            $this->term->newLine();

            if($executed > 0){
                $this->term->writeln(sprintf("'{$executed}' migration%s was altered successfully.", $executed > 1 ? 's' : ''), 'white', 'green');
                return STATUS_SUCCESS;
            }

            $this->term->writeln("No pending migration table to alter.", 'black', 'yellow');
        } catch (AppException|Exception $e) {
            $this->term->writeln("Migration alter execution failed: " . $e->getMessage(), 'white', 'red');
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
    private function executeSeeder(?string $class = null): int
    {
        $class ??= $this->term->getAnyOption('class', 'c');

        if($class === true || $class === ''){
            $this->term->writeln("Error: Class argument does not support empty value.", 'white', 'red');
            return STATUS_ERROR;
        }

        $noBackup = $this->term->getAnyOption('no-backup', 'n', false);
        $invokes = $this->term->getAnyOption('invoke', 'i', false);

        if($this->term->getAnyOption('rollback', 'r', false)){
            return $this->rollbackSeeder($class, $noBackup, $invokes);
        }

        /**
         * List all seeders that are called within another seeder.
         * @var class-string<Seeder>[] $seeders
        */
        $seeders = [];
        $lock = [];
        $backup = null; 
        $executed = 0;
        $path = root('/app/Database/Seeders/');
        $namespace = '\\App\\Database\\Seeders';

        if(!$noBackup){
            $backup = root('/writeable/database/Seeders/');
            
            if(file_exists($lockfile = $backup . 'seeders.lock')){
                $lock = get_content($lockfile);
                $lock = ($lock !== false && $lock !== '') ? json_decode($lock, true) : [];
            }
        }

        try {
            if ($class === false || $class === null) {
                /**
                 * @var class-string<Seeder>[] $extenders
                 */
                $extenders = Caller::extenders(Seeder::class, $path, $namespace);
                foreach ($extenders as $seed) {

                    if($lock !== [] && $this->guardVersion($seed, $lock, $path, $backup)){
                        continue;
                    }

                    /**
                     * @var Seeder $seeder
                     */
                    $seeder = new $seed();
                    if($this->doSeeding($seeder)){
                        if($invokes === true){
                            $seeders = array_merge($seeders, $seeder->getInvokes());
                        }
    
                        $executed++;
                        if(!$noBackup){
                            self::lockFile($lock, $seed, $path, $backup, true);
                        }
                    }

                    usleep(100000);
                }
            } else {
                $seed = "{$namespace}\\{$class}";
               
                if($lock !== [] && $this->guardVersion($seed, $lock, $path, $backup)){
                    return STATUS_SUCCESS;
                }

                /**
                 * @var Seeder $seeder
                */
                $seeder = new $seed();
                if($this->doSeeding($seeder)){
                    if($invokes === true){
                        $seeders = $seeder->getInvokes();
                    }

                    $executed++;
                    if(!$noBackup){
                        self::lockFile($lock, $seed, $path, $backup, true);
                    }
                }

                usleep(100000);
            }

            foreach ($seeders as $seed) {
                if($lock !== [] && $this->guardVersion($seed, $lock, $path, $backup)){
                    continue;
                }

                /**
                 * @var Seeder $seeder
                */
                $seeder = new $seed();
                if($this->doSeeding($seeder)){
                    $seeder->run(self::$builder);
                    $executed++;
                    if(!$noBackup){
                        self::lockFile($lock, $seed, $path, $backup, true);
                    }
                }

                usleep(100000);
            }

            $this->term->newLine();
            if($executed > 0){
                $this->term->writeln(sprintf("'{$executed}' seeder%s was executed successfully.", $executed > 1 ? 's' : ''), 'white', 'green');
                return STATUS_SUCCESS;
            }

            $this->term->writeln("Failed: No seeder was execution.", 'red');
        } catch (AppException|Exception $e) {
            $this->term->writeln("Seeder execution failed: " . $e->getMessage(), 'white', 'red');
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
    private function executeMigration(?bool $drop = null): int 
    {
        $class = $this->term->getAnyOption('class', 'c');
       
        if($class === true || $class === ''){
            $this->term->writeln("Error: Class argument does not support empty value.", 'white', 'red');
            return STATUS_ERROR;
        }

        $noBackup = $this->term->getAnyOption('no-backup', 'n', false);
        $invokes = $this->term->getAnyOption('invoke', 'i', false);

        if(!$drop && $this->term->getAnyOption('rollback', 'r', false)){
            self::$isDebug = false;
            return $this->rollbackMigration($class, $noBackup, $invokes);
        }

        /**
         * List all seeders that are called within another seeder.
         * @var class-string<Migration>[] $migrants
         */
        $migrants = [];
        $lock = [];
        $backup = null; 
        $executed = 0; 
        $drop ??= $this->term->getAnyOption('drop', 'd', false);
        self::$isDebug = $drop ? false : self::$isDebug;
        $path = root('/app/Database/Migrations/');
        $shouldGuard = (self::$isDebug === false && $drop === false);
        $namespace = '\\App\\Database\\Migrations';

        if(!self::$isDebug && $noBackup === false){
            $backup = root('/writeable/database/Migrations/');
            if(file_exists($lockfile = $backup . 'migrations.lock')){
                $lock = get_content($lockfile);
                $lock = ($lock !== false && $lock !== '') ? json_decode($lock, true) : [];
            }
        }

        try {
            if($class === false){
                $extenders = Caller::extenders(Migration::class, $path, $namespace);

                foreach ($extenders as $migrate) {
                    if($shouldGuard && $lock !== [] && $this->guardVersion($migrate, $lock, $path, $backup)){
                        continue;
                    }

                    if($this->doMigration($migrate, $drop, $invokes, $migrants)){
                        $executed++;

                        if($noBackup === false){
                            self::lockFile($lock, $migrate, $path, $backup, false, $drop);
                        }
                    }

                    usleep(100000);
                }
            }else{
                $migrate = "{$namespace}\\{$class}";

                if($shouldGuard && $lock !== [] && $this->guardVersion($migrate, $lock, $path, $backup)){
                    return STATUS_SUCCESS;
                }

                if($this->doMigration($migrate, $drop, $invokes, $migrants)){
                    $executed++;

                    if($noBackup === false){
                        self::lockFile($lock, $migrate, $path, $backup, false, $drop);
                    }
                }

                usleep(100000);
            }

            foreach ($migrants as $migrate) {
                if($shouldGuard && $lock !== [] && $this->guardVersion($migrate, $lock, $path, $backup)){
                    continue;
                }

                if($this->doMigration($migrate, $drop, false, null)){
                    $executed++;

                    if($noBackup === false){
                        self::lockFile($lock, $migrate, $path, $backup, false, $drop);
                    }
                }

                usleep(100000);
            }

            if(self::$isDebug){
                return STATUS_SUCCESS;
            }

            $this->term->newLine();
            if($executed > 0){
                if ($drop) {
                    $this->term->writeln(sprintf("'{$executed}' migration%s was downgraded successfully.", $executed > 1 ? 's' : ''), 'white', 'green');
                } else{
                    $this->term->writeln(sprintf("'{$executed}' migration%s was upgraded successfully.", $executed > 1 ? 's' : ''), 'white', 'green');
                }

                return STATUS_SUCCESS;
            }

            $this->term->writeln("Failed: no migration execution", 'red');
        } catch (AppException|Exception $e) {
            $db = shared('DROP_TRANSACTION');

            if($db instanceof DatabaseInterface && $db->inTransaction()){
                $db->rollback();
            }
            $this->term->writeln("Migration execution failed: " . $e->getMessage(), 'white', 'red');
        }

        return STATUS_ERROR;
    }

    /**
     * Executes migrations.
     *
     * @param string $namespace The migrations class namespace.
     * @param bool $drop Weather to migrations drop table.
     * @param bool $invokes Weather to invoke migrations classes.
     * @param array|null $migrants Pass migrations invokers by reference.
     * 
     * @return bool Returns true if migrations otherwise false.
     */
    private function doMigration(
        string $namespace, 
        bool $drop = false, 
        bool $invokes = false,
        ?array &$migrants = null,
    ): bool 
    {
        /**
         * @var Migration $instance
         */
        $instance = new $namespace();

        if(self::$isDebug){
            $instance->up();
            return false;
        }

        try{
            $instance->down();
            if($drop === false){
                sleep(1);
                $instance->up();
            }

            if($invokes === true){
                $migrants = $instance->getInvokes();
            }

            /**
             * @var DatabaseInterface $db
             */
            $db = shared('DROP_TRANSACTION');
            $hasTnx = ($db instanceof DatabaseInterface && $db->inTransaction());

            if(shared('MIGRATION_SUCCESS') === true){
                if($hasTnx){
                    return $db->commit();
                }

                return true;
            }
            
            if($hasTnx){
                $db->rollback();
            }
            
        } catch (Exception|AppException $e) {
            $db = shared('DROP_TRANSACTION');
            if ($db instanceof DatabaseInterface && $db->inTransaction()) {
                $db->rollback();
            }
            
            $this->term->writeln("Error: " . $e->getMessage(), 'white', 'red');
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
        try{
            $seeder->run(self::$builder);
            $this->term->writeln("[" . Color::style(get_class_name($seeder), 'green') . "] Execution completed.");
            return true;
        } catch (Exception|AppException $e) {
            $this->term->writeln("Error: " . $e->getMessage(), 'white', 'red');
        }
        return false;
    }

    /**
     * Rolls back a migration to a specified version or the last one executed.
     *
     * @param mixed $class The class name of the migration to rollback.
     * @param bool $noBackup Weather no backup flag is passed.
     * @param bool $noInvokes Weather to ignore invoking invokers.
     * @param int|string|null $input Pass input version number to invoke invokers.
     *
     * @return int Returns STATUS_SUCCESS on successful rollback, STATUS_ERROR on failure.
     */
    private function rollbackMigration(
        mixed $class, 
        bool $noBackup = false, 
        bool $invokes = false, 
        int|string|null $input = null
    ): int 
    {
        if (empty($class)) {
            $this->term->writeln('Error: Please specify a migration class name using `--class=Foo --rollback`.', 'white', 'red');
            return STATUS_ERROR;
        }

        $backupPath = root('/writeable/database/Migrations/');

        if (file_exists($lockFile = $backupPath . 'migrations.lock')) {
            $lock = get_content($lockFile);

            if ($lock === false || $lock === '') {
                $this->term->writeln('Error: Nothing to rollback, migration backup lock is empty.', 'white', 'red');
                return STATUS_ERROR;
            }

            $lock = json_decode($lock, true);

            if (isset($lock[$class])) {
                $metadata = $lock[$class]['metadata'] ?? [];

                if($metadata === []){
                    $this->term->writeln("Error: Backup metadata for '{$class}' not found.", 'white', 'red');
                    return STATUS_ERROR;
                }

                $versions = $this->listLocks($lock, $class);
                $input ??= $this->term->prompt('Enter the version number you want to roll back to:', $versions, 'required|in_array(' . implode(',', $versions) . ')');

                if ($input === $lock[$class]['latestVersion']) {
                    $this->term->writeln('Error: You cannot roll back to the current version.', 'white', 'red');
                    return STATUS_ERROR;
                }

                if (in_array($input, $versions)) {
                    try {
                        $backupFile = $backupPath . $metadata[$input]['backup'];
                        $migrationPath = root('/app/Database/Migrations/');
                        $migrateClass = "\\App\\Database\\Migrations\\{$class}";
                        $executions = 0;

                        if ($this->guardVersion($migrateClass, $lock, $migrationPath, $backupPath, (int) $input)) {
                            return STATUS_SUCCESS;
                        }
                        
                        if (copy($backupFile, $migrationPath . $class . '.php')) {
                            $migrants = [];

                            if ($this->doMigration($migrateClass, false, $invokes, $migrants)) {
                                $executions++;

                                if(!$noBackup){
                                    self::updateLockFile($lock, (int) $input, $class, $migrateClass, $migrationPath, $backupPath);
                                }

                                foreach ($migrants as $migrate) {
                                    $class = get_class_name($migrate);
    
                                    if ($class === '') {
                                        continue;
                                    }
    
                                    if ($this->rollbackMigration($class, $noBackup, true, $input) === STATUS_SUCCESS) {
                                        $executions++;
                                    }
    
                                    usleep(100000);
                                }

                                if ($executions > 0) {
                                    $this->term->writeln("Success: Migration rolled back to version '{$input}' successfully.", 'green');
                                    return STATUS_SUCCESS;
                                }
                            }
                        }

                        $this->term->writeln("Failed: No migrations were rolled back to version '{$input}'.", 'red');
                    } catch (Exception|AppException $e) {
                        $db = shared('DROP_TRANSACTION');
                        
                        if ($db instanceof DatabaseInterface && $db->inTransaction()) {
                            $db->rollback();
                        }
                        $this->term->writeln("Error: {$e->getMessage()}", 'red');
                    }
                } else {
                    $this->term->writeln("Error: The selected version '{$input}' does not exist.", 'white', 'red');
                }
            } else {
                $this->term->writeln("Error: No lock found for class '{$class}'.", 'white', 'red');
            }
        } else {
            $this->term->writeln("Error: Migration lock file not found.", 'white', 'red');
        }

        return STATUS_ERROR;
    }

    /**
     * Rolls back a seeder table to a specified version or the last one executed.
     *
     * @param mixed $class The class name of the seeder to rollback.
     * @param bool $noBackup Weather no backup flag is passed.
     * @param bool $invokes Weather to invokes other invocable seeders (default: false).
     *
     * @return int Returns STATUS_SUCCESS on successful rollback, STATUS_ERROR on failure.
     */
    private function rollbackSeeder(mixed $class, bool $noBackup = false, bool $invokes = false): int 
    {
        if (empty($class)) {
            $this->term->writeln('Error: You must specify a seeder class name to rollback using `--class=Foo --rollback`.', 'white', 'red');
            return STATUS_ERROR;
        }

        shared('SHOW_QUERY_DEBUG', (bool) $this->term->getAnyOption('debug', 'b', false));
        $table = $this->term->getAnyOption('table', 't', false);
        $backupPath = root('/writeable/database/Seeders/');
        $truncated = false;

        if (file_exists($lockFile = $backupPath . 'seeders.lock')) {
            $lock = get_content($lockFile);

            if($lock === false || $lock === ''){
                $this->term->writeln('Error: Seeder backup lock is empty.', 'white', 'red');
                return STATUS_ERROR;
            }

            $lock = json_decode($lock, true);

            if (isset($lock[$class])) {
                $metadata = $lock[$class]['metadata'] ?? [];
                $versions = $this->listLocks($lock, $class, 'Seed', ($table === true || $table === false));
                $executions = 0;

                $input = $this->term->prompt('Enter the version number you want to rollback to:', $versions, 'required|in_array(' . implode(',', $versions) . ')');

                if (in_array($input, $versions)) {
                    try {
                        $backupFile = $backupPath . $metadata[$input]['backup'];
                        $path = root('/app/Database/Seeders/');
                        $seederClass = "\\App\\Database\\Seeders\\{$class}";
                        $continue = 'yes';

                        if($this->guardVersion($seederClass, $lock, $path, $backupPath, (int) $input)){
                            return STATUS_SUCCESS;
                        }

                        if($table !== true && $table !== false){
                            if(self::$builder->table($table)->temp()){
                               $truncated = $this->doTruncate($table) === STATUS_SUCCESS;
                            }else{
                                $this->term->writeln("Error: Unable to create backup table '{$table}'. Recovery of seed records may be impossible if rollback fails.", 'red');
                                $continue = $this->term->prompt('Do you wish to continue?', ['yes' => 'green', 'no' => 'red'], 'required|in_array(yes,no)');
                                
                                if($continue === 'yes'){
                                    $this->doTruncate($table);
                                }
                            }
                        }
                 
                        if ($continue === 'yes' && copy($backupFile, $path . $class . '.php')) {
                            /**
                             * @var Seeder $seeder
                             */
                            $seeder = new $seederClass();

                            if($this->doSeeding($seeder)){
                                $executions++;
                                if(!$noBackup){
                                    self::updateLockFile($lock, (int) $input, null, $seederClass, $path, $backupPath, true);
                                }
                            
                                if($invokes === true){
                                    foreach ($seeder->getInvokes() as $seed) {
                                        if($this->doSeeding(new $seed())){
                                            $executions++;
                                            if(!$noBackup){
                                                self::updateLockFile($lock, (int) $input, null, $seed, $path, $backupPath, true);
                                            }
                                        }

                                        usleep(100000);
                                    }
                                }

                                if($executions > 0){
                                    $this->term->writeln("Success: Seeder rolled back to version '{$input}' successfully.", 'green');
                                    return STATUS_SUCCESS;
                                }
                            }
                        }

                        $this->term->writeln("Failed: No seeder was rolled back to version '{$input}'.", 'red');
                    } catch (Exception|AppException $e) {
                        $this->term->writeln("Error: {$e->getMessage()}", 'red');
                    }
                }else{
                    $this->term->writeln("Error: The selected version: '{$input}' does not exists.", 'white', 'red');
                }
            } else {
                $this->term->writeln("Error: No lock found for class: '{$class}'", 'white', 'red');
            }
        }

        if($truncated && self::$builder->exec("INSERT INTO {$table} SELECT * FROM temp_{$table}") > 0){
            $this->term->writeln("Table: '{$table}' records has been restored to last version");
        }

        return STATUS_ERROR;
    }

    /**
     * List all available lock versions that can be rolled back to if available.
     * 
     * 
     * @param array $lock  The lock array where metadata will be stored.
     * @param string $class  The class base name.
     * @param string $title  The context title for table identifier.
     * 
     * @return array<int,array> Return list of all available versions number.
     */
    private function listLocks(
        array $lock, 
        string $class, 
        string $title = 'Migration',
        bool $warn = false
    ): array
    {
        $headers = ['Version', 'Backup', $title . ' Date'];
        $versions = [];
        $metadata = $lock[$class]['metadata'] ?? [];

        $this->term->writeln("{$title} Class: " . $lock[$class]['namespace']);

        if($warn){
            $this->term->writeln(
                "Note: To avoid adding new seed records instead of replacing them, truncate the seeder table before rolling back.\n" .
                "Alternatively, pass the `--table` argument with your seed table name to truncate before rolling back the seeder.",
                'yellow'
            );            
        }

        foreach ($metadata as $item) {
            $versions[] = $item['version'];
            $rows[] = [
                'Version' => $item['version'],
                'Backup' => $item['backup'],
                $title . ' Date' => $item['timestamp']
            ];
        }

        $this->term->print($this->term->table($headers, $rows, null, 'green'));
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
    ): bool {
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
        string|null $class, 
        string $namespace, 
        string $path, 
        string $backup,
        bool $seeder = false
    ): bool {
        $className = $class ?? get_class_name($namespace);
        $entry = $lock[$className];
        $metadata = $entry['metadata'][$version];
        $timestamp = date('Y-m-d-H:i:s'); 
        $backupName = str_replace(':', '', $timestamp) . $className . '.php';
        $oldBackup = $backup . $metadata['backup'];
        $newVersion = $entry['latestVersion'] + 1;

        $lock[$className]['namespace'] = $namespace;
        $lock[$className]['lastVersion'] = $version;
        $lock[$className]['latestVersion'] = $newVersion;
        $lock[$className]['metadata'][$newVersion] = [
            'backup' => $backupName,
            'timestamp' => $timestamp,
            'version' => $newVersion
        ];

        if (!make_dir($backup)) {
            return false;
        }
    
        if (!copy($path . $className . '.php', $backup . $backupName)) {
            return false;
        }

        if(file_exists($oldBackup) && unlink($oldBackup)){
            unset($lock[$className]['metadata'][$version]);
        }
       
        $lockFilename = $seeder ? 'seeders.lock' : 'migrations.lock';
        return write_content($backup . $lockFilename, json_encode($lock, JSON_PRETTY_PRINT));
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
        if(file_exists($source) && file_exists($destination)){
            return md5_file($source) !== md5_file($destination);
        }

        return true;
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

        if($entry === []){
            return false;
        }

        $metadata = $entry['metadata'];
        $last = ($version === null) 
            ? ($metadata[$entry['latestVersion']]??null) 
            : $metadata[$version]??null;

        if($last === null){
            return false;
        }

        if(!self::versionChanged($path . $className . '.php', $backup . $last['backup'])){
            if(!$alter){
                $this->term->writeln("Skipped: No changed was applied to {$namespace}.", 'black', 'yellow');
            }

            return true;
        }

        return false;
    }
}