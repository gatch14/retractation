<?php
/**
 * @author    GSD
 * @copyright GSD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

spl_autoload_register(function (string $class): void {
    $map = [
        'Retractation' => __DIR__ . '/classes/Retractation.php',
        'RetractationEligibilityService' => __DIR__ . '/classes/RetractationEligibilityService.php',
    ];

    if (isset($map[$class]) && file_exists($map[$class])) {
        require_once $map[$class];
    }
});
