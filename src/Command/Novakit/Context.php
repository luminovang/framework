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

use \Luminova\Base\BaseConsole;
use \Luminova\Attributes\Generator;
use \Luminova\Storages\FileManager;

class Context extends BaseConsole 
{
    /**
     * {@inheritdoc}
    */
    protected string $group = 'Context';

    /**
     * {@inheritdoc}
    */
    protected string $name = 'context';

    /**
     * {@inheritdoc}
    */
    protected array $usages = [
        'php novakit context --help'
    ];

    /**
     * {@inheritdoc}
    */
    public function run(?array $options = []): int
    {
        $this->explain($options);

        $command = trim($this->getCommand());
        $noError = (bool) $this->getAnyOption('no-error', 'n', false);
        $isExport = (bool) $this->getAnyOption('export-attr', 'e', false);
        $isClear = (bool) $this->getAnyOption('clear-attr', 'c', false);

        $runCommand = match($command){
            'context' => ($isExport ? $this->buildAttributes() : (
                $isClear ? $this->clearAttributes() : 
                $this->installContext($this->getArgument(1), $noError)
            )),
            default => null
        };

        if ($runCommand === null) {
            return $this->oops($command);
        } 
            
        return (int) $runCommand;
    }

    /**
     * {@inheritdoc}
    */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    /**
     * Install the router context.
     *
     * @param mixed $name Context name.
     * @param bool $noError No error handler.
     * 
     * @return int Status code
     */
    private function installContext(mixed $name, bool $noError = false): int 
    {
        if(empty($name)){
            $this->error('Context name is required');
            $this->beeps();

            return STATUS_ERROR;
        }

        $camelCase = camel_case('on' . $name) . 'Error';
        $controller = ucfirst($name) . 'Controller::index';
        $onError = ($noError ? '' : ', ' . "[ViewErrors::class, '$camelCase']");
        $index = root('public') . 'index.php';
        $indexContent = file_get_contents($index);

        $handler = <<<PHP
        <?php 
        /** @var \Luminova\Routing\Router \$router */
        /** @var \App\Controllers\Application \$app */
        
        \$router->get('/', '$controller');
        PHP;

        $newContext = <<<PHP
            new Context('$name', $onError)
        PHP;

        $position = strpos($indexContent, 'Boot::http()->router->context(') + strlen('Boot::http()->router->context(');
        $content = substr_replace($indexContent, "\n$newContext,", $position, 0);

        if (strpos($name, ' ') !== false) {
            $this->writeln('Your context name contains space characters', 'red');

            return STATUS_ERROR;
        }

        if (has_uppercase($name)) {
            $this->beeps();
            $input = $this->chooser('Your context name contains uppercase character, are you sure you want to continue?', ['Continue', 'Abort'], true);

            if($input == 0){
                if(write_content($index, $content)){
                    write_content(root('routes') . $name . '.php', $handler);
                    $this->writeln("Route context installed: {$name}", 'green');

                    return STATUS_SUCCESS;
                }
            }

            $this->writeln('No changes was made');
            
            return STATUS_ERROR;
        }else{
            if(write_content($index, $content)){
                write_content(root('routes') . $name . '.php', $handler);
                $this->writeln("Route context installed: {$name}", 'green');

                return STATUS_SUCCESS;
            }
        }

        $this->writeln("Unable to install router context {$name}", 'red');
        return STATUS_ERROR;
    }

    /**
     * Clear cached attribute.
     * 
     * @return int Return status code.
     */
    private function clearAttributes(): int 
    {
        $backup = root('/writeable/caches/routes/');
        FileManager::remove($backup, false, $deleted);
        
        if ($deleted > 0) {
            $this->writeln("Success: '{$deleted}' cached attribute(s) was cleared.", 'white', 'green');
            return STATUS_SUCCESS;
        }

        $this->writeln("Error: No cached attributes to clear.", 'white', 'red');
        return STATUS_ERROR;
    }

    /**
     * Build routes from attribute.
     * 
     * @return int Return status code.
     */
    private function buildAttributes(): int
    {
        $collector = (new Generator('\\App\\Controllers\\'))->export('app/Controllers');

        $head = "<?php\nuse \Luminova\Routing\Router;\n/** @var \Luminova\Routing\Router \$router */\n/** @var \App\Controllers\Application \$app */\n\n";
        $httpContents = '';
        $apiContents = '';
        $cliContents = '';
        $cliHeader = '';
        $newContext = '';

        foreach($collector->getRoutes() as $ctx => $groups){

            foreach($groups as $group => $values){
                $hasGroup = ($ctx !== 'cli' && $group !== '/' && count($values) > 1);

                if($hasGroup){
                    $hasGroup = true;
                    if($ctx === 'http'){
                        $httpContents .= "\n\$router->bind('/{$group}', static function(Router \$router){\n";
                    }elseif($ctx === 'api'){
                        $apiContents .= "\n\$router->bind('/{$group}', static function(Router \$router){\n";
                    }
                }elseif($ctx === 'cli'){
                    $cliContents .= "\n\$router->group('{$group}', static function(Router \$router){\n";
                }

                foreach($values as $line){
                    if($ctx === 'http'){
                        
                        $pattern = $hasGroup ? substr($line['pattern'], strlen('/' . $group)) : $line['pattern'];
                        $pattern = '/' . trim($pattern, '/');
                        $httpContents .= ($hasGroup ? '   ' : '');

                        if($line['middleware'] !== null){
                            $methods = implode('|', $line['methods']);
                            $httpContents .= "\$router->middleware('{$methods}', '{$pattern}', '{$line['callback']}');\n";
                        }else{
                            $method = $this->getMethodType($line['methods']);
                            $httpContents .= "\$router->{$method}'{$pattern}', '{$line['callback']}');\n";
                        }
                    }elseif($ctx === 'api'){
                        $pattern = $hasGroup ? substr($line['pattern'], strlen('/api/' . $group)) : ltrim($line['pattern'], 'api/');
                        $pattern = '/' . trim($pattern, '/');
                        $apiContents .= ($hasGroup ? '   ' : '');

                        if($line['middleware'] !== null){
                            $methods = implode('|', $line['methods']);
                            $apiContents .= "\$router->middleware('{$methods}', '{$pattern}', '{$line['callback']}');\n";
                        }else{
                            $method = $this->getMethodType($line['methods']);
                            $apiContents .= "\$router->{$method}'{$pattern}', '{$line['callback']}');\n";
                        }
                    }elseif($ctx === 'cli'){
                        if($line['middleware'] !== null){
                            if($line['middleware'] === 'before' || $line['middleware'] === 'global'){
                                $cliHeader .= "\$router->before('global', '{$line['callback']}');\n";
                            }else{
                                $cliContents .= "   \$router->before('{$group}', '{$line['callback']}');\n";
                            }
                        }else{
                            $cliContents .= "   \$router->command('{$line['pattern']}', '{$line['callback']}');\n";
                        }
                    }
                }

                if($hasGroup){
                    if($ctx === 'http'){
                        $httpContents .= "});\n\n";
                        
                    }elseif($ctx === 'api'){
                        $apiContents .= "});\n\n";
                    }
                }elseif($ctx === 'cli'){
                    $cliContents .= "});\n\n";
                }
            }
        }

        $path = root('/routes/');
        make_dir($path);

        if ($httpContents !== '' && write_content($path . 'web.php', $head . $httpContents)) {
            $newContext .= "    new Context(Context::WEB, [ViewErrors::class, 'onWebError']),\n";
        }

        if ($apiContents !== '' && write_content($path . 'api.php', $head . $apiContents)) {
            $newContext .= "    new Context(Context::API, [ViewErrors::class, 'onApiError']),\n";
        }

        if ($cliContents !== '' && write_content($path . 'cli.php', $head . $cliHeader . $cliContents)) {
            $newContext .= "    new Context(Context::CLI),\n";
        }

        if ($newContext !== '') {
            $index = root('public') . 'index.php';
            $indexContent = file_get_contents($index);
            $search = "Boot::http()->router->context(";
            $startPos = strpos($indexContent, $search);

            if ($startPos !== false) {
                $startPos += strlen($search);
                $endPos = strpos($indexContent, ")->run();", $startPos);

                if ($endPos !== false) {
                    // Extract the part before and after the context
                    $beforeContext = substr($indexContent, 0, $startPos);
                    $afterContext = substr($indexContent, $endPos);

                    // Construct the new content
                    $newContextContent = rtrim("\n$newContext", ",\n") . "\n";
                    $newIndexContent = $beforeContext . $newContextContent . $afterContext;
                    
                    if(write_content($index, $newIndexContent)){
                        $this->writeln("Routes exported successfully.", 'green');
                        setenv('feature.route.attributes', 'disable', true);
                        return STATUS_SUCCESS;
                    }
                }
            }
        }

        $this->writeln("Failed: Unable to create route from attribute.", 'red');
        return STATUS_ERROR;
    }

    /**
     * Get Route method.
     * 
     * @param array|null $methods The attribute methods,
     * 
     * @return string class method name.
    */
    private function getMethodType(array|null $methods): string 
    {
        if($methods === null){
            return 'get(';
        }
        if(count($methods) >= 6){
            return "any(";
        }

        if(count($methods) > 1){
            $methods = implode('|', $methods);
            return "capture('{$methods}', ";
        }

        $methods = strtolower($methods[0]);
        return "$methods(";
    }
}