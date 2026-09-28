<?php
/**
 * @author    Christophe Gatelet
 * @copyright Christophe Gatelet
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.1.4:
 * - re-import French translations under the native Symfony domain
 *   Modules.Retractation2026.Shop (front) / Modules.Retractation2026.Admin (admin),
 * - clear every translation cache so the catalogue is rebuilt from the module XLF files.
 */
function upgrade_module_1_1_4($module)
{
    $module->installTranslations();

    Tools::clearCache();
    if (method_exists('Tools', 'clearXMLCache')) {
        Tools::clearXMLCache();
    }
    if (method_exists('Tools', 'clearCompileCache')) {
        Tools::clearCompileCache();
    }

    $root = rtrim(_PS_ROOT_DIR_, '/\\');
    $envs = ['prod', 'dev'];
    $candidates = [];
    foreach ($envs as $env) {
        $candidates[] = $root . '/var/cache/' . $env . '/translations';
        $candidates[] = $root . '/app/cache/' . $env . '/translations';
    }

    foreach ($candidates as $dir) {
        if (is_dir($dir)) {
            upgrade_module_1_1_4_remove_dir($dir);
        }
    }

    return true;
}

function upgrade_module_1_1_4_remove_dir($dir)
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        $path = $file->getPathname();
        if ($file->isDir()) {
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}
