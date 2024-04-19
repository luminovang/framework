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
use \Luminova\Seo\Sitemap;
use \Luminova\Security\Crypter;

class System extends BaseConsole 
{
    /**
     * @var string $group command group
    */
    protected string $group = 'System';

    /**
     * @var string $name command name
    */
    protected string $name = 'generate:key';

    /**
     * @var string|array $usage command usages
    */
    protected string|array $usages  = [
        'php novakit generate:key',
        'php novakit generate:sitemap',
        'php novakit env:add --key="my_new_key" --value="my key value"',
        'php novakit env:remove'
    ];

    /**
     * Options
     *
     * @var array<string, string> $options
    */
    protected array $options = [
        '--no-save'  => 'Do not save generated application key to .env file.',
        '--key'  => 'Env key to update, add or delete.',
        '--value'  => 'Env key value to generate.',
    ];

    /**
     * @param array $options command options
     * 
     * @return int 
    */
    public function run(?array $options = []): int
    {
        $this->explain($options);
        $command = trim($this->getCommand());
        $noSave = (bool) $this->getOption('no-save', false);
        $key = $this->getOption('key');
        $value = $this->getOption('value');

        $runCommand = match($command){
            'generate:key' => $this->generateKey($noSave),
            'generate:sitemap' => $this->generateSitemap(),
            'env:add' => $this->addEnv($key, $value),
            'env:remove' => $this->removeEnv($key),
            default => null
        };

        if ($runCommand === null) {
            $this->error('Unknown command ' . $this->color("'$command'", 'red') . ' not found', null);

            return STATUS_ERROR;
        } 

        return (int) $runCommand;
    }

    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

   /**
     * Add environment variable.
     * 
     * @param string $key Environment variable name. 
     * @param mixed $value Environment variable value.
     * 
     * @return int Status code.
     */
    private function addEnv(string $key, string $value = ''): int 
    {
        if(empty($key)){
            $this->beeps();
            $this->error('Environment variable key cannot be an empty string');

            return STATUS_ERROR;
        }

        setenv($key, $value, true);
        $this->header();
        $this->success('Variable "' . $key . '" added successfully');

        return STATUS_SUCCESS;
    }

    /**
     * Remove environment variable.
     * 
     * @param string $key Environment variable name. 
     * 
     * @return int Status code.
     */
    private function removeEnv(string $key): int 
    {
        if(empty($key)){
            $this->beeps();
            $this->error('Environment variable key cannot be an empty string');

            return STATUS_ERROR;
        }

        $envFile = root(__DIR__) . '.env';
        $envContents = file_get_contents($envFile);
        
        if($envContents === false){
            $this->beeps();
            $this->error('Failed to read environment file');
            return STATUS_ERROR;
        }
        
        if (strpos($envContents, "$key=") !== false || strpos($envContents, "$key =") !== false) {

            $newContents = preg_replace("/\b$key\b.*\n?/", '', $envContents);
            if (write_content($envFile, $newContents) !== false) {
                unset($_ENV[$key]);
                unset($_SERVER[$key]);

                $this->header();
                $this->success('Variable "' . $key . '" was deleted successfully');

                return STATUS_SUCCESS;
            }
        }

        $this->beeps();
        $this->error('Variable "' . $key . '" not found or may have been deleted');

        return STATUS_ERROR;
    }

    /**
     * Generates sitemap 
     * 
     * @return int Status code 
    */
    private function generateSitemap(): int 
    {
        if(Sitemap::generate(null, $this)){
            return STATUS_SUCCESS;
        }

        $this->beeps();
        $this->error('Sitemap creation failed');
    
        return STATUS_ERROR;
    }

    /**
     * Generates encryption sitekey.
     * 
     * @param bool $noSave Save key to env or just print.
     * 
     * @return int Status code 
    */
    private function generateKey(bool $noSave): int 
    {
        $key = Crypter::generate_key(); 

        if($key === false){
            $this->beeps();
            $this->error('Failed to generate application encryption key');

            return STATUS_ERROR;
        }

        $this->success('Application key generated successfully.');

        if($noSave){
            $this->newLine();
            $this->print($key . PHP_EOL);
        }else{
            setenv('app.key', $key, true);
        }
    
        return STATUS_SUCCESS;
    }
}