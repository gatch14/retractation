<?php
/**
 * @author    Christophe Gatelet
 * @copyright Christophe Gatelet
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Retractation2026RetractationlistModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $authRedirection = 'my-account';

    public function initContent()
    {
        parent::initContent();

        $sql = 'SELECT r.*, o.reference AS order_reference
                FROM `' . _DB_PREFIX_ . 'retractation` r
                LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON r.id_order = o.id_order
                WHERE r.id_customer = ' . (int) $this->context->customer->id . '
                    AND r.id_shop = ' . (int) $this->context->shop->id . '
                ORDER BY r.date_add DESC';

        $retractations = Db::getInstance()->executeS($sql);

        $this->context->smarty->assign([
            'retractations' => $retractations ?: [],
        ]);

        $this->setTemplate('module:retractation2026/views/templates/front/retractationlist.tpl');
    }
}
