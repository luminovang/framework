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

use \Luminova\Base\BaseCommand;

class System extends BaseCommand 
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
    protected string|array $usages  = 'php novakit generate:key';

    /**
     * Options
     *
     * @var array<string, string> $options
    */
    protected array $options = [
        '--no-save'  => 'Do not save generated key to .env file',
    ];

    /**
     * @param array $options command options
     * 
     * @return int 
    */
    public function run(?array $options = []): int
    {
        $this->explain($options);

        $noSave = (bool) $this->getOption('no-save');

        $key = base64_encode(func()->generate_key());

        if($noSave){
            $this->print($key . PHP_EOL);
        }else{
            setenv('app.key', $key, true);
            $this->print('Application key generated successfully.' . PHP_EOL);
        }
    
        return STATUS_SUCCESS;
    }
}