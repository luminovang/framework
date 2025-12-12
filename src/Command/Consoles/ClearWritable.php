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

use Luminova\Luminova;
use Luminova\Base\Console;
use Luminova\Command\Terminal;
use Luminova\Storage\Filesystem;

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
        $name = trim($this->input->getName());

        return (int) match ($name) {
            'clear:caches' => $this->clearCaches($this->input->getAnyOption('dir', 'd')),
            'clear:routes' => $this->clearFromWritable('/caches/routes/'),
            'clear:storage' => $this->clearFromWritable('/storages/'),
            'clear:dir',
            'clear:writable' => $this->clearFromWritable(
                $this->input->getAnyOption('dir', 'd'),
                $this->input->hasOption('remove-parent', 'rp')
            ),
            'clear:temp' => $this->clearFromWritable('/temp/'),
            default => Terminal::oops($name),
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
     * Clear all cached routes.
     *
     * @param string|null $file The specific cache directory.
     * @return int Status code.
     */
    private function clearCaches(?string $file): int
    {
        $file = self::isFile($file) 
            ? "{$file}/" 
            : '';

        return $this->clearFromWritable("/caches/{$file}");
    }

    /**
     * Check if filename is value.
     *
     * @param string|null $file
     * @return bool
     */
    private static function isFile(?string $file): bool 
    {
        if($file === null || $file === ''){
            return false;
        }

        $file = trim($file);

        return $file !== '' && $file !== '/';
    }

    /**
     * Clear files and directories within the writeable directory.
     *
     * @param string|null $file The directory path relative to the writeable directory.
     * @param bool $removeParent Whether to remove the target directory after clearing its contents.
     *
     * @return int The command status code.
     */
    private function clearFromWritable(?string $file, bool $removeParent = false): int
    {
        if (!self::isFile($file)) {
            Terminal::error('Specify a valid directory using --dir=<pathname>.');
            return STATUS_ERROR;
        }

        $file = trim(Filesystem::normalizePath($file), TRIM_DS);
        $path = Luminova::root("/writeable/{$file}/");
        $name = basename(rtrim($path, TRIM_DS));

        if (!is_dir($path)) {
            Terminal::error(sprintf(
                'Directory "%s" does not exist in the writeable directory.',
                $name
            ));

            return STATUS_ERROR;
        }

        $writeable = rtrim(Luminova::root('/writeable/'), TRIM_DS);

        if (rtrim($path, TRIM_DS) === $writeable) {
            Terminal::error('The writeable directory cannot be cleared or removed directly.');
            return STATUS_ERROR;
        }

        if (!is_writable($path)) {
            $owner = function_exists('posix_getpwuid')
                ? posix_getpwuid((int) fileowner($path))
                : false;

            $owner = $owner ? sprintf(' (current owner: %s)', $owner['name']) : '';

            $changeable = Terminal::prompt(
                sprintf(
                    'Directory "%s" is not writable%s. Do you want to change its owner?',
                    $name,
                    $owner
                ),
                ['yes', 'no'],
                'required|in_array(yes,no)'
            );

            if ($changeable !== 'yes') {
                Terminal::error('Operation cancelled. Directory permissions were not changed.');
                return STATUS_ERROR;
            }

            $newOwner = Terminal::input('Enter the username of the new owner:');

            if ($newOwner === '' || !chown($path, $newOwner)) {
                Terminal::error(sprintf(
                    'Unable to change the owner of "%s". Verify the username and your permissions.',
                    $name
                ));

                return STATUS_ERROR;
            }

            if (!is_writable($path)) {
                Terminal::error(sprintf(
                    'Directory "%s" is still not writable after changing its owner.',
                    $name
                ));

                return STATUS_ERROR;
            }
        }

        $removeParent = $removeParent
            && rtrim(dirname($path), TRIM_DS) !== $writeable;

        $deleted = Filesystem::delete($path, $removeParent);

        if ($deleted > 0) {
            Terminal::success(sprintf(
                'Successfully deleted %d item(s) from "%s".',
                $deleted,
                $name
            ));
            Terminal::beeps();
        } else {
            Terminal::writeln(sprintf(
                'No files or directories were deleted from "%s".',
                $name
            ));
        }

        return STATUS_SUCCESS;
    }
}