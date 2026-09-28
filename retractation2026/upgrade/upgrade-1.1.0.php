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
 * Upgrade to 1.1.0:
 * - add default values for exclusion configuration keys;
 * - add unique index on (id_order, id_shop, status) to prevent duplicate requests.
 */
function upgrade_module_1_1_0($module)
{
    if (!Configuration::hasKey('RETRACTATION_EXCLUDED_PRODUCTS')) {
        Configuration::updateValue('RETRACTATION_EXCLUDED_PRODUCTS', '');
    }
    if (!Configuration::hasKey('RETRACTATION_EXCLUDED_CATEGORIES')) {
        Configuration::updateValue('RETRACTATION_EXCLUDED_CATEGORIES', '');
    }

    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'retractation';

    // Drop legacy unique index from v1.0.0 if it still exists.
    $legacy = $db->executeS('SHOW INDEX FROM `' . $table . '` WHERE Key_name = \'uq_order_active\'');
    if (!empty($legacy)) {
        $db->execute('ALTER TABLE `' . $table . '` DROP INDEX `uq_order_active`');
    }

    $current = $db->executeS('SHOW INDEX FROM `' . $table . '` WHERE Key_name = \'uq_order_status\'');
    if (empty($current)) {
        return $db->execute(
            'ALTER TABLE `' . $table . '` ADD UNIQUE KEY `uq_order_status` (`id_order`, `id_shop`, `status`)'
        );
    }

    return true;
}
