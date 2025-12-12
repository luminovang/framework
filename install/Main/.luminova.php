<?php
/**
 * Luminova Version Control Interface Configuration
 *
 * Defines how the bootloader resolves framework core paths and autoload strategy.
 * This file is loaded during early bootstrap and overrides default resolution logic.
 *
 * Modes:
 * - resolve.paths: enables shared framework installation
 * - resolve.autoloader:
 *      auto     → detect Composer, fallback to Luminova if needed
 *      composer → use Composer only (recommended, but requires updating composer psr4 mapping)
 *      luminova → use custom loader only
 *
 * Rules:
 * - Only system/bootstrap may be shared
 * - Application code (app, routes, storage) must remain local
 * - Version mismatch may cause runtime instability
 *
 * @link https://luminova.ng/docs/0.0.0/boot/shared-modules
 * @link https://github.com/luminovang/luminova-vci
 * @link https://github.com/luminovang/luminova-vci-admin
 *
 * @return array{
 *     resolve.paths: bool,
 *     resolve.autoloader: 'auto'|'composer'|'luminova',
 *     luminova.version: string,
 *     luminova.paths: array{
 *         root: string,
 *         target: string
 *     }
 * }
 */
return [
    /**
     * Enable shared framework installation.
     */
    'resolve.paths' => false,

    /**
     * Autoloader strategy.
     */
    'resolve.autoloader' => 'auto',

    /**
     * Required framework version constraint.
     */
    'luminova.version' => '>=3.8',

    /**
     * Framework installation paths.
     */
    'luminova.paths' => [
        'root' => '/opt/luminova/packages',
        'target' => '/opt/luminova/packages/3.8.4',
    ],
];