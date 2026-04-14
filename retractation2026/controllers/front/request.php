<?php
/**
 * @author    GSD
 * @copyright GSD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Retractation2026RequestModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $authRedirection = 'my-account';

    private $retractationData = [];

    public function postProcess()
    {
        if (!Tools::isSubmit('submitRetractation')) {
            return;
        }

        if (Tools::getValue('token') !== Tools::getToken(false)) {
            $this->errors[] = $this->trans('Invalid security token. Please try again.', [], 'Modules.Retractation2026.Front');
            return;
        }

        $idOrder = (int) Tools::getValue('id_order');
        if ($idOrder <= 0) {
            $this->errors[] = $this->trans('Invalid order.', [], 'Modules.Retractation2026.Front');
            return;
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            $this->errors[] = $this->trans('Order not found.', [], 'Modules.Retractation2026.Front');
            return;
        }

        if ((int) $order->id_customer !== (int) $this->context->customer->id) {
            $this->errors[] = $this->trans('You do not have permission to access this order.', [], 'Modules.Retractation2026.Front');
            return;
        }

        require_once _PS_MODULE_DIR_ . 'retractation2026/classes/RetractationEligibilityService.php';
        $service = new RetractationEligibilityService();
        $eligibility = $service->getEligibility($idOrder);

        if (!$eligibility['eligible']) {
            $this->errors[] = $this->trans('This order is not eligible for retractation.', [], 'Modules.Retractation2026.Front');
            return;
        }

        $existing = Db::getInstance()->getValue(
            'SELECT id_retractation FROM `' . _DB_PREFIX_ . 'retractation`
             WHERE id_order = ' . (int) $idOrder . ' AND status != \'cancelled\''
        );
        if ($existing) {
            $this->errors[] = $this->trans('A retractation request already exists for this order.', [], 'Modules.Retractation2026.Front');
            return;
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            'id_order' => (int) $idOrder,
            'id_customer' => (int) $this->context->customer->id,
            'id_shop' => (int) $this->context->shop->id,
            'reason' => pSQL(Tools::getValue('reason')),
            'status' => 'pending',
            'retractation_date' => $now,
            'deadline_date' => pSQL($eligibility['deadline']),
            'deadline_source' => pSQL($eligibility['source']),
            'ip_address' => pSQL(Tools::getRemoteAddr()),
            'date_add' => $now,
            'date_upd' => $now,
        ];

        $inserted = Db::getInstance()->insert('retractation', $data);
        if (!$inserted) {
            $this->errors[] = $this->trans('An error occurred while processing your request. Please try again.', [], 'Modules.Retractation2026.Front');
            return;
        }

        if (Configuration::get('RETRACTATION_EMAIL_ENABLED')) {
            $customer = $this->context->customer;
            $templateVars = [
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{order_reference}' => $order->reference,
                '{retractation_date}' => date('d/m/Y', strtotime($now)),
                '{retractation_time}' => date('H:i:s', strtotime($now)),
                '{reason}' => Tools::getValue('reason'),
                '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                '{shop_url}' => Tools::getShopDomainSsl(true),
            ];

            Mail::Send(
                (int) $this->context->language->id,
                'retractation_confirmation',
                $this->trans('Confirmation of your retractation — Order %s', [$order->reference], 'Modules.Retractation2026.Front'),
                $templateVars,
                $customer->email,
                $customer->firstname . ' ' . $customer->lastname,
                null,
                null,
                null,
                null,
                _PS_MODULE_DIR_ . 'retractation2026/mails/'
            );
        }

        $this->retractationData = [
            'retractation_date' => date('d/m/Y', strtotime($now)),
            'retractation_time' => date('H:i:s', strtotime($now)),
            'order_reference' => $order->reference,
        ];
    }

    public function initContent()
    {
        parent::initContent();

        if (!empty($this->retractationData)) {
            $this->context->smarty->assign($this->retractationData);
            $this->setTemplate('module:retractation2026/views/templates/front/confirmation.tpl');
            return;
        }

        $idOrder = (int) Tools::getValue('id_order');
        if ($idOrder <= 0) {
            $this->errors[] = $this->trans('Invalid order.', [], 'Modules.Retractation2026.Front');
            $this->redirectWithNotifications($this->context->link->getPageLink('my-account'));
            return;
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            $this->errors[] = $this->trans('Order not found.', [], 'Modules.Retractation2026.Front');
            $this->redirectWithNotifications($this->context->link->getPageLink('my-account'));
            return;
        }

        if ((int) $order->id_customer !== (int) $this->context->customer->id) {
            $this->errors[] = $this->trans('You do not have permission to access this order.', [], 'Modules.Retractation2026.Front');
            $this->redirectWithNotifications($this->context->link->getPageLink('my-account'));
            return;
        }

        require_once _PS_MODULE_DIR_ . 'retractation2026/classes/RetractationEligibilityService.php';
        $service = new RetractationEligibilityService();
        $eligibility = $service->getEligibility($idOrder);

        if (!$eligibility['eligible']) {
            $this->errors[] = $this->trans('This order is not eligible for retractation.', [], 'Modules.Retractation2026.Front');
            $this->redirectWithNotifications($this->context->link->getPageLink('my-account'));
            return;
        }

        $existing = Db::getInstance()->getValue(
            'SELECT id_retractation FROM `' . _DB_PREFIX_ . 'retractation`
             WHERE id_order = ' . (int) $idOrder . ' AND status != \'cancelled\''
        );
        if ($existing) {
            $this->errors[] = $this->trans('A retractation request already exists for this order.', [], 'Modules.Retractation2026.Front');
            $this->redirectWithNotifications($this->context->link->getPageLink('my-account'));
            return;
        }

        $this->context->smarty->assign([
            'customer' => $this->context->customer,
            'order' => $order,
            'order_reference' => $order->reference,
            'retractation_deadline' => $eligibility['deadline'],
            'token' => Tools::getToken(false),
        ]);

        $this->setTemplate('module:retractation2026/views/templates/front/request.tpl');
    }
}
