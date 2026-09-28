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
 * Upgrade to 1.1.2: force French default texts in front templates.
 */
function upgrade_module_1_1_2($module)
{
    Tools::clearCache();
    return true;
}
