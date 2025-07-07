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

use \Luminova\Base\BaseConsole;
use \Luminova\Command\Utils\Color;
use \SplFileObject;
use \Exception;
use function \Luminova\Funcs\root;

class Logs extends BaseConsole 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'log';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'Logs';

    /**
     * {@inheritdoc}
     */
    protected array|string $usages = [
        'php novakit log --help'
    ];

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        $this->term->perse($options);
        $level = $this->term->getAnyOption('level', 'l', null);

        if(!$level){
            $this->term->beeps(1);
            $this->term->error('No log level was specified.');
            return STATUS_ERROR;
        }

        setenv('throw.cli.exceptions', 'true');
        $start = $this->term->getAnyOption('start', 's', null);
        $end = $this->term->getAnyOption('end', 'e', 5);

        try{
            if($this->term->getAnyOption('clear', 'c', false)){
                return $this->clearLogFile($level);
            }
        
            return $this->readLogFile($level, $end, $start);
        }catch(Exception $e){
            $this->term->beeps(1);
            $this->term->error('Log operation failed:');
            $this->term->writeln($e->getMessage());
        }

        return STATUS_ERROR;
    }

    /**
     * {@inheritdoc}
     */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    /**
     * Retrieves the log file path for the specified log level.
     *
     * @param string $level The log level, used to identify the log file.
     * 
     * @return string|false Return the file path if accessible, or false on failure.
     */
    private function getLogFile(string $level): string|bool 
    {
        $filePath = root('/writeable/logs/', $level . '.log');

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->term->writeln(sprintf('Log: "%s" not found or not readable', $level), 'red');
            return false;
        }

        return $filePath;
    }

    /**
     * Clears the log file for the specified log level.
     *
     * @param string $level The log level, used to identify the log file.
     * 
     * @return int STATUS_SUCCESS if the file was successfully deleted, STATUS_ERROR on failure.
     */
    private function clearLogFile(string $level): int 
    {
        $filePath = $this->getLogFile($level);

        if ($filePath === false) {
            return STATUS_ERROR;
        }

        if (unlink($filePath)) {
            $this->term->success(sprintf('Log %s was cleared successfully.', $level));
            return STATUS_SUCCESS;
        }

        $this->term->error(sprintf('Failed to clear log: %s.', $level));
        return STATUS_ERROR;
    }

    /**
     * Reads log entries from the specified log level file.
     * Reads log entries either from a specified offset or from the most recent entries based on the limit.
     *
     * @param string $level The log level, used to identify the log file.
     * @param int $limit The number of log entries to read. Defaults to 5.
     * @param int|null $offset The offset to start reading from. If null, reads from the end.
     * 
     * @return int STATUS_SUCCESS if log entries are successfully read, STATUS_ERROR on failure.
     */
    private function readLogFile(string $level, int $limit = 5, ?int $offset = null): int
    {
        $lines = '';
        $counts = 0;
        $filePath = $this->getLogFile($level);

        if ($filePath === false) {
            return STATUS_ERROR;
        }

        $file = new SplFileObject($filePath, 'r');
        $file->seek($offset ?? PHP_INT_MAX);

        if ($offset === null) {
            $linesArray = [];
            
            while ($file->key() > 0 && $counts < $limit) {
                 // Move up one line
                $file->seek($file->key() - 1);
                $line = $file->current();
                $normalizedLine = $this->normalizer($line, $level);

                if ($normalizedLine !== '') {
                    // Insert at the beginning of the array
                    array_unshift($linesArray, $normalizedLine);
                    $counts++;
                }
            }

            $lines = implode("\n", $linesArray);
        }else{
            while (!$file->eof() && $counts < $limit) {
                $line = $file->fgets();
                $normalizedLine = $this->normalizer($line, $level);

                if ($normalizedLine !== '') {
                    $lines .= $normalizedLine . "\n";
                    $counts++;
                }
            }
        }

        if ($lines === '') {
            $this->term->writeln(sprintf('Log: "%s" is empty.', $level), 'yellow');
            return STATUS_SUCCESS;
        }

        $this->term->writeln($lines);
        return STATUS_SUCCESS;
    }

    /**
     * Normalizes a log entry line by adding color coding and formatting.
     *
     * @param string $line The log entry line to normalize.
     * @param string $level The log level, used to identify the log file.
     * 
     * @return string The normalized log entry line.
     */
    private function normalizer(string $line, string $level): string
    {
        $line = trim($line);

        if ($line === '') {
            return $line;
        }

        $pattern = "[{$level}] [";
        $codePattern = '/\[' . strtoupper($level) . '\s*\((\d+)\)\]/';

        if (str_starts_with($line, $pattern)) {
            [$date, $line] = explode(']:', $line, 2);
            $date = str_replace($pattern, '', $date);
            $line = trim($line);
            $date = Color::style($date, 'green');

            $line = "[$date] $line";
        }

        if (preg_match($codePattern, $line, $matches)) {
            $errorCode = Color::style("({$matches[1]})", 'red');
            $line = preg_replace($codePattern, "[Code {$errorCode}]", $line);
        }

        return $line;
    }
}