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
 * Upgrade to 1.1.1:
 * - re-import French translations using the Symfony domain format
 *   (Modules.Retractation2026.Front / Modules.Retractation2026.Admin).
 */
function upgrade_module_1_1_1($module)
{
    $idLang = (int) Language::getIdByIso('fr');
    if ($idLang) {
        // Remove legacy translations stored under the old domain format.
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'translation`
             WHERE id_lang = ' . $idLang . '
               AND domain IN (\'ModulesRetractation2026Front\', \'ModulesRetractation2026Admin\')'
        );
    }

    $module->installTranslations();

    // Clear translation cache so the new domains are picked up immediately.
    Tools::clearCache();

    return true;
}
