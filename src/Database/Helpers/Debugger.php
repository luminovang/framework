<?php 
/**
 * Luminova Framework Builder debugger.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database\Helpers;

use \Closure;
use Luminova\Luminova;
use Luminova\Logger\Logger;
use Luminova\Database\Builder;
use Luminova\Database\Helpers\Util;

final class Debugger 
{
    /**
     * Printable debug title.
     * 
     * @var string[] $titles 
     */
    private array $titles = [];

    /**
     * The debug query information.
     * 
     * @var array{method:string,binding:array,query:array} $details 
     */
    private array $details = [];

    /**
     * Creates a query debugger instance.
     *
     * @param int $mode Debugging mode used to determine query formatting behavior.
     * @param string $objectId Unique builder identifier for the current debug context.
     */
    public function __construct(private int $mode, private string $objectId)
    {
    }

    /**
     * Updates the query debug identifier.
     *
     * The identifier is appended to generated placeholders to prevent conflicts
     * when multiple queries or conditions use similar parameter names.
     *
     * @param string $objectId Unique builder identifier for the current debug context.
     *
     * @return void
     */
    public function setObjectId(string $objectId): void 
    {
        $this->objectId = $objectId;
    }

    /**
     * Get an array of debug query information.
     * 
     * Returns detailed debug information about the query string, including formats for `MySQL` 
     * and `PDO` placeholders, as well as the exact binding mappings for each column.
     * 
     * @return array{method:string,binding:array,query:array} Return array containing query information.
     * 
     * @see self::dumpDebug()
     */
    public function getDebug(): array 
    {
        return $this->details;
    }

    /**
     * Output collected query debug information in the requested format.
     *
     * Displays builder query debugging information and includes the latest
     * statement debug details when supported. Output formatting can be adjusted
     * for CLI, HTML, or JSON environments.
     *
     * Supported formats:
     *
     * - `null`   Default output using readable array format.
     * - `html`   Wrap output in an escaped HTML `<pre>` block.
     * - `json`   Output formatted JSON.
     *
     * The selected format only applies to builder dump debugging mode.
     * CLI and command execution always use plain text output.
     *
     * @param string|null $format Output format (`html`, `json`, or null).
     *
     * @return void
     *
     * @see self::getDebug()
     * @see self::dumpDebug()
     */
    public function dump(?string $format = null): void
    {
        if (!$this->isBuilderDebugging()) {
            return;
        }

        if ($format === null || PHP_SAPI === 'cli' || Luminova::isCommand()) {
            print_r($this->details);
            return;
        }

        switch (strtolower($format)) {
            case 'json':
                echo json_encode(
                    $this->details,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                );
                break;

            case 'html':
                echo '<pre>';
                echo htmlspecialchars(
                    print_r($this->details, true),
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );
                echo '</pre>';
                break;

            default:
                print_r($this->details);
        }
    }

    /**
     * Builds a debug representation of a prepared MySQLi query and its bindings.
     *
     * Replaces query placeholders with escaped parameter values for debugging
     * purposes and returns binding metadata similar to MySQLi statement details.
     *
     * @param array $statements Query data containing:
     *                          - `query`  The SQL query string.
     *                          - `params` Bound query parameters.
     *
     * @return array Returns query text, sent query, and parameter binding details.
     */
    public static function debugMySqliDumpParams(array $statements): array
    {
        $params = $statements['params'] ?? [];
        $query = $statements['raw'] 
            ?? $statements['query'] 
            ?? '';

        $paramNo = 0;
        $bindings = [];
        $sentQuery =  $query;

        foreach ($params as $name => $value) {
            $placeholder = ':' . ltrim($name, ':');

            $sentQuery = self::parseMySqliDebugSentQuery(
                $sentQuery,
                $value,
                $placeholder
            );

            $bindings[] = [
                'name'          => $placeholder,
                'value'         => $value,
                'paramno'       => $paramNo,
                'is_param'      => 1,
                'param_type'    => Util::getMySqliTypeFromValue($value, true)
            ];
            $paramNo++;
        }

        return [
            'SQL' => sprintf(
                '[%d] %s',
                strlen($query),
                $query
            ),
            'Sent SQL' => sprintf(
                '[%d] %s',
                strlen($sentQuery),
                $sentQuery
            ),
            'Params' => count($bindings),
            'Bindings' => $bindings
        ];
    }

    /**
     * Check if builder level debugging is enabled.
     *
     * @return bool
     */
    private function isBuilderDebugging(): bool 
    {
        return $this->mode !== Builder::DEBUG_NONE 
            && $this->mode !== Builder::DEBUG_DRIVER_DUMP;
    }

    /**
     * Outputs debug information once per unique title.
     *
     * Useful for tracing structured data like bind parameters
     * or internal states during query building.
     *
     * @param mixed $input The value to dump (string or array).
     * @param string|null $title Optional label shown only once per title.
     *
     * @return void
     */
    public function printLine(mixed $input, ?string $title = null): void 
    {
        if($title && !isset($this->titles[$title])){
            $this->titles[$title] = 1;
            echo "\n{$title}\n\n";
        }

        if(is_array($input)){
            print_r($input);
            echo "\n";
            return;
        }

        echo "{$input}\n";
    }

    /**
     * Stores database query details for debugging.
     *
     * This method prepares the executed SQL query, bound parameters, and condition
     * values for debugging output. In production environments, the details are
     * written to the debug logger. In non-production environments, the details
     * remain available internally for inspection.
     *
     * @param string $query The SQL query containing placeholders.
     * @param string $method The query operation type (e.g. insert, update, delete).
     * @param array $values Values used for insert or update bindings.
     * @param array $conditions Query conditions used for parameter binding.
     * @param Closure|null $onClosureValue Optional callback to resolve closure-based
     *                                      condition values.
     *
     * @return void
     * @throws \Luminova\Exceptions\JsonException If a value cannot be encoded.
     */
    public function enqueue(
        string $query, 
        string $method, 
        array $values = [],
        array $conditions = [],
        ?Closure $onClosureValue = null
    ): void
    {
        $params = [];

        if($method === 'insert'){
            $length = count($values);

            for ($i = 0; $i < $length; $i++) {
                foreach($values[$i] as $column => $value){
                    $params[$i][$column] = Builder::escape($value);
                }
            }

            $this->log($query, $method, $params);
            return;
        }
        
        if($method === 'update'){
            foreach($values as $column => $value){
                $params[$column] = Builder::escape($value);
            }
        }

        foreach ($conditions as $index => $condition) {
            $value = $condition['value'];

            if($onClosureValue && $value instanceof Closure) {
                $value = $onClosureValue($value);
            }

            switch ($condition['mode']) {
                case Builder::AGAINST:
                    $params[Builder::toNamedParameter(
                        "match_column_{$index}", 
                        $this->objectId)
                    ] = Builder::escape($value);
                break;
                case Builder::RAW:
                    $params["raw_{$index}"] = $condition['value'];
                break;
                case Builder::CONJOIN:
                    $this->bindDebugGroupConditions($condition['conditions'], $index, $params);
                break;
                case Builder::NESTED:
                    $bindIndex = 0;
                    $this->bindDebugGroupConditions($condition['left'], $index, $params, $bindIndex);
                    $this->bindDebugGroupConditions($condition['right'], $index, $params, $bindIndex);
                break;
                case Builder::INARRAY:
                    foreach ($value as $idx => $val) {
                        $placeholder = Builder::toNamedParameter(
                            "{$condition['column']}_in_{$idx}", 
                            $this->objectId
                        );

                        $params[$placeholder] = is_array($val) 
                            ? Builder::escapeValueList($val, true) 
                            : $val;
                    }
                break;
                default: 
                    $params[Builder::toNamedParameter($condition['column'], $this->objectId)] = Builder::escape($value);
                break;
            }
        }

        $this->log($query, $method, $params);
    }

    /**
     * Stores query debugging details and optionally writes them to the logger.
     *
     * Captures the query method, bound parameters, and placeholder formats for
     * debugging prepared SQL statements. In production environments, the details
     * are written using the debug log level. Otherwise, they remain available
     * internally for inspection.
     *
     * @param string $query The SQL query containing named placeholders.
     * @param string $method The query operation type (e.g. select, insert, update, delete).
     * @param array $params The bound query parameters.
     *
     * @return void
     */
    private function log(string $query, string $method, array $params = []): void
    {
        $this->details = [
            'method'    => $method,
            'binding'   => $params,
            'query'     => [
                'placeholder'   => $query,
                'positional'    => preg_replace('/:([a-zA-Z0-9_]+)/', '?', $query),
            ],
        ];

        if(!PRODUCTION){
            return;
        }

        Logger::tryLog(
            'debug',
            'Database query debugging.',
            $this->details
        );
    }

    /**
     * Binds conditions for debugging purposes in a group.
     * 
     * @param array $bindings The array of bindings.
     * @param int $index The index.
     * @param array &$params The array to store the debug parameters.
     * @param int &$bindIndex The last index.
     * 
     * @return void
     */
    private function bindDebugGroupConditions(
        array $bindings, 
        int $index, 
        array &$params = [], 
        int &$bindIndex = 0
    ): void 
    {
        $length = count($bindings);

        for ($idx = 0; $idx < $length; $idx++) {
            $bind = $bindings[$idx];
            $column = key($bind);

            $pIndex = $idx + $bindIndex;
            $placeholder = Builder::toNamedParameter(
                "{$column}_{$index}_{$pIndex}",
                $this->objectId
            );
   
            $params[$placeholder] = Builder::escape($bind[$column]['value']);
            $bindIndex++;
        }
    }

    /**
     * Replace a query placeholder with its escaped debug value.
     *
     * Supports both positional placeholders (`?`) and named placeholders
     * (`:name`) when generating a debug representation of a prepared query.
     *
     * @param string $query The SQL query string.
     * @param mixed $value The bound parameter value.
     * @param string $name The named placeholder to replace.
     *
     * @return string Returns the query with the placeholder replaced.
     */
    private static function parseMySqliDebugSentQuery(
        string $query,
        mixed $value,
        string $name = ''
    ): string 
    {
        $escaped = Builder::escape(
            $value,
            enQuote: true,
            addSlashes: true
        );

        if (($pos = strpos($query, '?')) !== false) {
            return substr_replace($query, $escaped, $pos, 1);
        }

        // Named placeholder replacing
        if ($name !== '') {
            return str_replace($name, $escaped, $query);
        }

        return $query;
    }

}