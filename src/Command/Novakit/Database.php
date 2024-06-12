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
    protected array $options = [];

    /**
     * {@inheritdoc}
    */
    public function run(?array $options = []): int
    {
        $result = match(true){
            'db:create' => function () {
                echo "TODO Database create";
            },
            'db:update' => function () {
                echo "TODO Database update";
            },
            'db:insert' => function () {
                echo "TODO Database insert";
            },
            'db:delete' => function () {
                echo "TODO Database delete";
            },
            'db:drop' => function () {
                echo "TODO Database drop";
            },
            'db:truncate' => function () {
                echo "TODO Database truncate";
            },
            'db:select' => function () {
                echo "TODO Database select";
            },
            default => function() {
                echo "Handle Unknown command\n";
                return STATUS_ERROR;
            }
        };
    
        return STATUS_SUCCESS;
    }

    /**
     * {@inheritdoc}
    */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }
}