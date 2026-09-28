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
 * Upgrade to 1.1.3:
 * - switch front notice templates back to English source strings,
 * - re-import French translations,
 * - aggressively clear Symfony translation caches so the catalogue is rebuilt.
 */
function upgrade_module_1_1_3($module)
{
    // Re-install French translations into ps_translation.
    $module->installTranslations();

    // Clear PrestaShop/Smarty caches.
    Tools::clearCache();
    if (method_exists('Tools', 'clearXMLCache')) {
        Tools::clearXMLCache();
    }
    if (method_exists('Tools', 'clearCompileCache')) {
        Tools::clearCompileCache();
    }

    // Force Symfony translator catalogue rebuild by deleting its compiled files.
    $root = rtrim(_PS_ROOT_DIR_, '/\\');
    $envs = ['prod', 'dev'];
    $candidates = [];
    foreach ($envs as $env) {
        $candidates[] = $root . '/var/cache/' . $env . '/translations';
        $candidates[] = $root . '/app/cache/' . $env . '/translations';
    }

    foreach ($candidates as $dir) {
        if (is_dir($dir)) {
            upgrade_module_1_1_3_remove_dir($dir);
        }
    }

    return true;
}

function upgrade_module_1_1_3_remove_dir($dir)
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
