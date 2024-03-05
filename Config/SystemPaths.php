<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Config;

abstract class SystemPaths {
     /**
     * SYSTEM FOLDER NAME
     */
    public string $systemDirectory = __DIR__ . '/../system';

    /**
     * SYSTEM FOLDER NAME
     */
    public string $systemPluginsDirectory = __DIR__ . '/../system/plugins';


    /**
     * APPLICATION FOLDER NAME
     */
    public string $appDirectory = __DIR__ . '/..';

    /**
     * WRITABLE DIRECTORY NAME
     */
    public string $writableDirectory = __DIR__ . '/../writable';

    /**
     * TESTS DIRECTORY NAME
     */
    public string $testsDirectory = __DIR__ . '/../tests';

    /**
     * VIEW DIRECTORY NAME
     */
    public string $viewDirectory = __DIR__ . '/../resources/views';
}