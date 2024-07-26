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
     * {@inheritdoc}
    */
    protected string $group = 'System';

    /**
     * {@inheritdoc}
    */
    protected string $name = 'generator';

    /**
     * {@inheritdoc}
    */
    protected string|array $usages  = [
        'php novakit generate:key --help',
        'php novakit generate:sitemap --help',
        'php novakit env:add --help',
        'php novakit env:remove --help'
    ];

    /**
     * {@inheritdoc}
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
     * Add environment variable.
     * 
     * @param string $key Environment variable name. 
     * @param string $value Environment variable value.
     * 
     * @return int Status code.
     */
    private function addEnv(string $key, string $value = ''): int 
    {
        if($key === ''){
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
        if($key === ''){
            $this->beeps();
            $this->error('Environment variable key cannot be an empty string');

            return STATUS_ERROR;
        }

        $envFile = root() . '.env';
        $envContents = get_content($envFile);
        
        if($envContents === false){
            $this->beeps();
            $this->error('Failed to read environment file');
            return STATUS_ERROR;
        }
        
        if (str_contains($envContents, "$key=") && str_contains($envContents, "$key =")) {
            $newContents = preg_replace("/\b$key\b.*\n?/", '', $envContents);
            if (write_content($envFile, $newContents) !== false) {
                unset($_ENV[$key], $_SERVER[$key]);
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
        $this->newLine();
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