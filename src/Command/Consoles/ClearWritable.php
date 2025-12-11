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

use \Luminova\Base\Console;
use \Luminova\Utility\Storage\Filesystem;
use function \Luminova\Funcs\root;

class ClearWritable extends Console
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'clear';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'Clear';

    /**
     * {@inheritdoc}
     */
    protected string|array $usages  = [
        'php novakit clear:caches --help',
        'php novakit clear:routes --help',
        'php novakit clear:storage --help',
        'php novakit clear:temp --help',
        'php novakit clear:writable --help'
    ];

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        $this->term->perse($options);
        $command = trim($this->term->getCommand());

        $runCommand = match ($command) {
            'clear:caches' => $this->clearCaches($this->term->getAnyOption('dir', 'd')),
            'clear:routes' => $this->clearFromWritable('/caches/routes/'),
            'clear:storage' => $this->clearFromWritable('/storages/'),
            'clear:writable' => $this->clearFromWritable(
                $this->term->getAnyOption('dir', 'd'),
                (bool)$this->term->getAnyOption('parent', 'p', false)
            ),
            'clear:temp' => $this->clearFromWritable('/temp/'),
            default => null,
        };

        return ($runCommand === null) 
            ? $this->term->oops($command) 
            : (int) $runCommand;
    }

    /**
     * {@inheritdoc}
     */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    /**
     * Clear all cached routes.
     *
     * @param string|null $file The specific cache directory.
     * @return int Status code.
     */
    private function clearCaches(?string $file): int
    {
        $file = $file ? "{$file}/" : '';
        return $this->clearFromWritable("/caches/{$file}");
    }

    /**
     * Clear files and directories in the writeable directory.
     *
     * @param string|null $file The directory path to clear.
     * @param bool $removeParent Whether to remove the parent directory itself.
     * @return int Status code.
     */
    private function clearFromWritable(?string $file, bool $removeParent = false): int
    {
        if (empty($file)) {
            $this->term->error('Please specify the directory name or path using --dir=<name>.');
            return STATUS_ERROR;
        }

        $file = trim(Filesystem::toCompatible($file), TRIM_DS);
        $path = root("/writeable/{$file}/");
        $name = basename($path);

        if (!file_exists($path)) {
            $this->term->error(sprintf('The folder "%s" does not exist in the writeable directory.', $name));
            return STATUS_ERROR;
        }

        if (str_ends_with(rtrim($path, TRIM_DS), 'writeable')) {
            $this->term->error('The "writeable" directory cannot be deleted directly.');
            return STATUS_ERROR;
        }

        if (!is_writable($path)) {
            $owner = posix_getpwuid((int) fileowner($path));
            $owner = $owner ? ' ' . $owner['name'] : '';
            $changeable = $this->term->prompt(
                sprintf("The folder '%s' is not writable. Would you like to change its owner{$owner}?", $name),
                ['yes', 'no'], 
                'required|in_array(yes,no)'
            );
        
            if ($changeable === 'no') {
                return STATUS_ERROR;
            }

            $newOwner = $this->term->input('Please enter the username for the new owner:');
    
            if (!chown($path, $newOwner)) {
                $this->term->error(sprintf(
                    "Unable to change the owner of '%s' to '%s'. Please verify the username and permissions.", 
                    $path, 
                    $newOwner
                ));
                return STATUS_ERROR;
            }
        }        

        $deleted = 0;
        @Filesystem::remove($path, $removeParent, $deleted);

        if ($deleted > 0) {
            $this->term->success(sprintf('Successfully deleted %d items in "%s".', $deleted, $name));
            $this->term->beeps();
        } else {
            $this->term->writeln(sprintf('No files were deleted in "%s".', $name));
        }

        return STATUS_SUCCESS;
    }
}