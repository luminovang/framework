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
use \Luminova\Utility\Storage\FileManager;
use \Luminova\Attributes\Internal\Compiler;
use function \Luminova\Funcs\{
    root,
    camel_case,
    write_content,
    get_content,
    make_dir,
    has_uppercase
};

class Context extends Console 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'context';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'Context';

    /**
     * {@inheritdoc}
     */
    protected array|string $usages = [
        'php novakit context --help'
    ];

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        $this->term->perse($options);

        $command = trim($this->term->getCommand());
        $noError = (bool) $this->term->getAnyOption('no-error', 'n', false);
        $isExport = (bool) $this->term->getAnyOption('export-attr', 'e', false);
        $isClear = (bool) $this->term->getAnyOption('clear-attr', 'c', false);

        $runCommand = match($command){
            'context' => ($isExport ? $this->buildAttributes() : (
                $isClear ? $this->clearAttributes() : 
                $this->installContext($this->term->getArgument(1), $noError)
            )),
            default => null
        };

        if ($runCommand === null) {
            return $this->term->oops($command);
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
            $this->term->error('Route prefix name is required');
            $this->term->beeps();

            return STATUS_ERROR;
        }

        $camelCase = camel_case('on' . $name) . 'Error';
        $controller = ucfirst($name) . 'Controller::index';
        $onError = ($noError ? '' : ', ' . "[ErrorController::class, '$camelCase']");
        $index = root('/public/', 'index.php');
        $indexContent = get_content($index);

        $handler = <<<PHP
        <?php 
        /** @var \Luminova\Routing\Router \$router */
        /** @var \App\Application \$app */
        
        \$router->get('/', '$controller');
        PHP;

        $newPrefix = <<<PHP
            new Prefix('$name', $onError)
        PHP;

        $position = strpos($indexContent, 'Boot::http()->router->context(') + strlen('Boot::http()->router->context(');
        $content = substr_replace($indexContent, "\n$newPrefix,", $position, 0);

        if (strpos($name, ' ') !== false) {
            $this->term->writeln('Your context name contains space characters', 'red');

            return STATUS_ERROR;
        }

        if (has_uppercase($name)) {
            $this->term->beeps();
            $input = $this->term->prompt(
                'Your context name contains uppercase character, are you sure you want to continue?', 
                ['yes', 'no'], 
                'required|in_array(yes,no)'
            );

            if($input === 'yes'){
                if(write_content($index, $content)){
                    write_content(root('/routes/', $name . '.php'), $handler);
                    $this->term->writeln("Route context installed: {$name}", 'green');

                    return STATUS_SUCCESS;
                }
            }

            $this->term->writeln('No changes was made');
            
            return STATUS_ERROR;
        }else{
            if(write_content($index, $content)){
                write_content(root('/routes/', $name . '.php'), $handler);
                $this->term->writeln("Route context installed: {$name}", 'green');

                return STATUS_SUCCESS;
            }
        }

        $this->term->writeln("Unable to install router context {$name}", 'red');
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
        $deleted = 0;
        
        FileManager::remove($backup, false, $deleted);
        
        if ($deleted > 0) {
            $this->term->writeln("Success: '{$deleted}' cached attribute(s) was cleared.", 'white', 'green');
            return STATUS_SUCCESS;
        }

        $this->term->writeln("Error: No cached attributes to clear.", 'white', 'red');
        return STATUS_ERROR;
    }

    /**
     * Build routes from attribute.
     * 
     * @return int Return status code.
     */
    private function buildAttributes(): int
    {
        $hmvc = env('feature.app.hmvc', false);
        $apiPrefix = env('app.api.prefix', 'api');
        $collector = (new Compiler('', false, $hmvc))->export($hmvc ? 'app/Modules' : 'app/Controllers');

        $head = "<?php\nuse \Luminova\Routing\Router;\n/** @var \Luminova\Routing\Router \$router */\n/** @var \App\Application \$app */\n\n";
        $httpContents = '';
        $apiContents = '';
        $cliContents = '';
        $cliHeader = '';
        $newPrefix = '';

        $path = root('/routes/');
        make_dir($path);

        foreach($collector->getRoutes() as $ctx => $modules){
            foreach($modules as $module => $groups){
                $httpContents = '';
                $apiContents = '';
                $cliContents = '';

                foreach($groups as $group => $values){
                    $hasGroup = ($ctx !== 'cli' && $group !== '/' && count($values) > 1);

                    if($hasGroup){
                        $hasGroup = true;
                        if($ctx === 'http'){
                            $httpContents .= "\n\$router->bind('/{$group}', static function(Router \$router){\n";
                        }elseif($ctx === 'api' || $ctx === $apiPrefix){
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
                        }elseif($ctx === 'api' || $ctx === $apiPrefix){
                            $pattern = $hasGroup 
                                ? substr($line['pattern'], strlen("/$apiPrefix/" . $group)) 
                                : ltrim($line['pattern'], "$apiPrefix/");
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
                                if($line['middleware'] === 'global'){
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
                            
                        }elseif($ctx === 'api' || $ctx === $apiPrefix){
                            $apiContents .= "});\n\n";
                        }
                    }elseif($ctx === 'cli'){
                        $cliContents .= "});\n\n";
                    }
                }

                $webContext = ($module === 'Controllers') ? 'web.php' : $module . '.php';
                $apiContext = ($module === 'Controllers') ? 'api.php' : $module . '.php';
                $webPrefix = ($module === 'Controllers') ? 'Prefix::WEB' : "'{$module}'";
                $apiPrefix = ($module === 'Controllers') ? 'Prefix::API' : "'{$module}'";

                if ($httpContents !== '' && write_content($path . $webContext, $head . $httpContents)) {
                    $newPrefix .= "    new Prefix($webPrefix, [ErrorController::class, 'onWebError']),\n";
                }

                if ($apiContents !== '' && write_content($path . $apiContext, $head . $apiContents)) {
                    $newPrefix .= "    new Prefix($apiPrefix, [ErrorController::class, 'onApiError']),\n";
                }

                if ($cliContents !== '' && write_content($path . 'cli.php', $head . $cliHeader . $cliContents)) {
                    $newPrefix .= "    new Prefix(Prefix::CLI),\n";
                }
            }
        }

        if ($newPrefix !== '') {
            $index = root('/public/', 'index.php');
            $indexContent = get_content($index);
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
                    $newPrefixContent = rtrim("\n$newPrefix", ",\n") . "\n";
                    $newIndexContent = $beforeContext . $newPrefixContent . $afterContext;
                    
                    if(write_content($index, $newIndexContent)){
                        $this->term->writeln("Routes exported successfully.", 'green');
                        setenv('feature.route.attributes', 'disable', true);
                        return STATUS_SUCCESS;
                    }
                }
            }
        }

        $this->term->writeln("Failed: Unable to create route from attribute.", 'red');
        return STATUS_ERROR;
    }

    /**
     * Get Route method.
     * 
     * @param array|null $methods The attribute methods,
     * 
     * @return string class method name.
     */
    private function getMethodType(?array $methods): string 
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