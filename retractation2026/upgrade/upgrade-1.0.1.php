<?php
/**
 * @author    GSD
 * @copyright GSD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to 1.0.1: remove overly strict unique constraint so a cancelled request
 * does not block a new one on the same order.
 */
function upgrade_module_1_0_1($module)
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'retractation';

    $indexes = $db->executeS('SHOW INDEX FROM `' . $table . '` WHERE Key_name = \'uq_order_active\'');
    if (!empty($indexes)) {
        return $db->execute('ALTER TABLE `' . $table . '` DROP INDEX `uq_order_active`');
    }

    return true;
}
