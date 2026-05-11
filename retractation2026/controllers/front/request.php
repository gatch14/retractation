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
    public $auth = false;

    private $retractationData = [];

    private function lookupOrderByReference(): ?Order
    {
        // WR-08: pSQL applied only at interpolation, not pre-stored
        $email = trim(Tools::getValue('lookup_email'));
        $reference = trim(Tools::getValue('lookup_reference'));

        if (empty($email) || empty($reference)) {
            $this->errors[] = $this->trans('Please provide your email and order reference.', [], 'Modules.Retractation2026.Front');
            return null;
        }

        if (!Validate::isEmail($email)) {
            $this->errors[] = $this->trans('Invalid email address.', [], 'Modules.Retractation2026.Front');
            return null;
        }

        $idOrder = (int) Db::getInstance()->getValue(
            'SELECT o.id_order FROM `' . _DB_PREFIX_ . 'orders` o
             JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = o.id_customer
             WHERE o.reference = \'' . pSQL($reference) . '\'
             AND c.email = \'' . pSQL($email) . '\''
        );

        if ($idOrder <= 0) {
            $this->errors[] = $this->trans('No order found with this reference and email.', [], 'Modules.Retractation2026.Front');
            return null;
        }

        return new Order($idOrder);
    }

    private function loadOrderAndVerifyAccess(): ?Order
    {
        $idOrder = (int) Tools::getValue('id_order');
        if ($idOrder <= 0) {
            $this->errors[] = $this->trans('Invalid order.', [], 'Modules.Retractation2026.Front');
            return null;
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            $this->errors[] = $this->trans('Order not found.', [], 'Modules.Retractation2026.Front');
            return null;
        }

        if ($this->context->customer->isLogged()) {
            if ((int) $order->id_customer !== (int) $this->context->customer->id) {
                $this->errors[] = $this->trans('You do not have permission to access this order.', [], 'Modules.Retractation2026.Front');
                return null;
            }
            return $order;
        }

        // CR-02: validate guest email before comparison
        $guestEmail = Tools::getValue('guest_email');
        $orderReference = Tools::getValue('order_reference');

        if (empty($guestEmail) || empty($orderReference)) {
            $this->errors[] = $this->trans('Please provide your email and order reference.', [], 'Modules.Retractation2026.Front');
            return null;
        }

        if (!Validate::isEmail($guestEmail)) {
            $this->errors[] = $this->trans('Invalid email address.', [], 'Modules.Retractation2026.Front');
            return null;
        }

        if ($order->reference !== $orderReference) {
            $this->errors[] = $this->trans('You do not have permission to access this order.', [], 'Modules.Retractation2026.Front');
            return null;
        }

        $customer = new Customer((int) $order->id_customer);
        if (!Validate::isLoadedObject($customer) || $customer->email !== $guestEmail) {
            $this->errors[] = $this->trans('You do not have permission to access this order.', [], 'Modules.Retractation2026.Front');
            return null;
        }

        return $order;
    }

    private function getCustomerForOrder(Order $order): Customer
    {
        if ($this->context->customer->isLogged()) {
            return $this->context->customer;
        }
        return new Customer((int) $order->id_customer);
    }

    private function hasIdOrder(): bool
    {
        return (int) Tools::getValue('id_order') > 0;
    }

    // WR-02: single eligibility + duplicate guard used by all code paths
    private function checkEligibility(Order $order): ?array
    {
        require_once _PS_MODULE_DIR_ . 'retractation2026/classes/RetractationEligibilityService.php';
        $service = new RetractationEligibilityService();
        $eligibility = $service->getEligibility((int) $order->id);

        if (!$eligibility['eligible']) {
            $this->errors[] = $this->trans('This order is not eligible for retractation.', [], 'Modules.Retractation2026.Front');
            return null;
        }

        $existing = Db::getInstance()->getValue(
            'SELECT id_retractation FROM `' . _DB_PREFIX_ . 'retractation`
             WHERE id_order = ' . (int) $order->id . '
               AND id_shop = ' . (int) $this->context->shop->id . '
               AND status != \'cancelled\''
        );
        if ($existing) {
            $this->errors[] = $this->trans('A retractation request already exists for this order.', [], 'Modules.Retractation2026.Front');
            return null;
        }

        return $eligibility;
    }

    // CR-01: per-form nonce stored in session cookie
    private function generateFormToken(): string
    {
        $token = bin2hex(random_bytes(16));
        $this->context->cookie->retractation_nonce = $token;
        return $token;
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitLookup')) {
            return;
        }

        if (!Tools::isSubmit('submitRetractation')) {
            return;
        }

        // CR-01: verify nonce (single-use, consumed on success)
        $nonce = $this->context->cookie->retractation_nonce;
        if (!$nonce || Tools::getValue('retractation_token') !== $nonce) {
            $this->errors[] = $this->trans('Invalid security token. Please try again.', [], 'Modules.Retractation2026.Front');
            return;
        }
        $this->context->cookie->retractation_nonce = false;

        $order = $this->loadOrderAndVerifyAccess();
        if (!$order) {
            return;
        }

        $eligibility = $this->checkEligibility($order);
        if (!$eligibility) {
            return;
        }

        $customer = $this->getCustomerForOrder($order);

        $now = date('Y-m-d H:i:s');
        // CR-07: strip HTML tags from reason before storage
        $reason = strip_tags(Tools::getValue('reason'));

        $data = [
            'id_order' => (int) $order->id,
            'id_customer' => (int) $customer->id,
            'id_shop' => (int) $this->context->shop->id,
            'reason' => pSQL($reason),
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
            $templateVars = [
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{order_reference}' => $order->reference,
                // WR-04: locale-aware date format
                '{retractation_date}' => Tools::displayDate($now, null, false),
                '{retractation_time}' => date('H:i:s', strtotime($now)),
                // CR-05: HTML-safe reason in email
                '{reason}' => htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'),
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
            'retractation_date' => Tools::displayDate($now, null, false),
            'retractation_time' => date('H:i:s', strtotime($now)),
            'order_reference' => $order->reference,
            'is_guest' => !$this->context->customer->isLogged(),
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

        if (Tools::isSubmit('submitLookup')) {
            $order = $this->lookupOrderByReference();
            if ($order) {
                if ($this->context->customer->isLogged()
                    && (int) $order->id_customer !== (int) $this->context->customer->id
                ) {
                    $this->errors[] = $this->trans('This order does not belong to your account.', [], 'Modules.Retractation2026.Front');
                    $this->context->smarty->assign(['show_lookup' => true]);
                    $this->setTemplate('module:retractation2026/views/templates/front/request.tpl');
                    return;
                }

                $customer = new Customer((int) $order->id_customer);
                $eligibility = $this->checkEligibility($order);
                if (!$eligibility) {
                    $this->context->smarty->assign(['show_lookup' => true]);
                    $this->setTemplate('module:retractation2026/views/templates/front/request.tpl');
                    return;
                }

                $this->context->smarty->assign([
                    'show_lookup' => false,
                    'customer_firstname' => $customer->firstname,
                    'customer_lastname' => $customer->lastname,
                    'customer_email' => $customer->email,
                    'order' => $order,
                    'order_reference' => $order->reference,
                    'retractation_deadline' => $eligibility['deadline'],
                    'retractation_token' => $this->generateFormToken(),
                    'is_guest' => true,
                    'guest_email' => trim(Tools::getValue('lookup_email')),
                ]);
                $this->setTemplate('module:retractation2026/views/templates/front/request.tpl');
                return;
            }

            $this->context->smarty->assign(['show_lookup' => true]);
            $this->setTemplate('module:retractation2026/views/templates/front/request.tpl');
            return;
        }

        if ($this->hasIdOrder()) {
            $order = $this->loadOrderAndVerifyAccess();
            if (!$order) {
                $this->context->smarty->assign(['show_lookup' => true]);
                $this->setTemplate('module:retractation2026/views/templates/front/request.tpl');
                return;
            }

            $eligibility = $this->checkEligibility($order);
            if (!$eligibility) {
                $this->context->smarty->assign(['show_lookup' => true]);
                $this->setTemplate('module:retractation2026/views/templates/front/request.tpl');
                return;
            }

            $customer = $this->getCustomerForOrder($order);
            $isGuest = !$this->context->customer->isLogged();

            $this->context->smarty->assign([
                'show_lookup' => false,
                'customer_firstname' => $customer->firstname,
                'customer_lastname' => $customer->lastname,
                'customer_email' => $customer->email,
                'order' => $order,
                'order_reference' => $order->reference,
                'retractation_deadline' => $eligibility['deadline'],
                'retractation_token' => $this->generateFormToken(),
                'is_guest' => $isGuest,
                'guest_email' => $isGuest ? Tools::getValue('guest_email') : '',
            ]);
            $this->setTemplate('module:retractation2026/views/templates/front/request.tpl');
            return;
        }

        $this->context->smarty->assign(['show_lookup' => true]);
        $this->setTemplate('module:retractation2026/views/templates/front/request.tpl');
    }
}
