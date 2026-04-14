<?php
/**
 * @author    GSD
 * @copyright GSD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Retractation2026 extends Module
{
    const CONFIG_KEYS = [
        'RETRACTATION_DELAY_DAYS',
        'RETRACTATION_BUFFER_SHIPPED',
        'RETRACTATION_BUFFER_ORDER',
        'RETRACTATION_ENABLED',
        'RETRACTATION_EMAIL_ENABLED',
    ];

    const CONFIG_DEFAULTS = [
        'RETRACTATION_DELAY_DAYS' => 14,
        'RETRACTATION_BUFFER_SHIPPED' => 7,
        'RETRACTATION_BUFFER_ORDER' => 14,
        'RETRACTATION_ENABLED' => 1,
        'RETRACTATION_EMAIL_ENABLED' => 1,
    ];

    const HOOKS = [
        'displayOrderDetail',
        'displayCustomerAccount',
        'displayAdminOrderSide',
        'actionOrderStatusPostUpdate',
        'displayProductAdditionalInfo',
        'displayShoppingCartFooter',
        'displayHeader',
        'displayFooter',
    ];

    public function __construct()
    {
        $this->name = 'retractation2026';
        $this->tab = 'legal_compliance';
        $this->version = '1.0.0';
        $this->author = 'GSD';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '9.99.99'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Rétractation 2026', [], 'Modules.Retractation2026.Admin');
        $this->description = $this->trans(
            'Gestion du droit de rétractation pour les commandes e-commerce, conforme à la législation française 2026.',
            [],
            'Modules.Retractation2026.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Êtes-vous sûr de vouloir désinstaller ce module ? Toutes les données de rétractation seront perdues.',
            [],
            'Modules.Retractation2026.Admin'
        );
    }

    public function install()
    {
        Shop::setContext(Shop::CONTEXT_ALL);

        if (!$this->executeSqlFile('install')) {
            return false;
        }

        Configuration::updateValue('RETRACTATION_DELAY_DAYS', 14);
        Configuration::updateValue('RETRACTATION_BUFFER_SHIPPED', 7);
        Configuration::updateValue('RETRACTATION_BUFFER_ORDER', 14);
        Configuration::updateValue('RETRACTATION_ENABLED', 1);
        Configuration::updateValue('RETRACTATION_EMAIL_ENABLED', 1);

        $tab = new Tab();
        $tab->class_name = 'AdminRetractationDashboard';
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentOrders');
        $tab->module = $this->name;
        $tab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = ($lang['iso_code'] === 'fr') ? 'Rétractations' : 'Retractations';
        }
        if (!$tab->add()) {
            return false;
        }

        return parent::install()
            && $this->registerHook('displayOrderDetail')
            && $this->registerHook('displayCustomerAccount')
            && $this->registerHook('displayAdminOrderSide')
            && $this->registerHook('actionOrderStatusPostUpdate')
            && $this->registerHook('displayProductAdditionalInfo')
            && $this->registerHook('displayShoppingCartFooter')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayFooter');
    }

    public function uninstall()
    {
        if (!$this->executeSqlFile('uninstall')) {
            return false;
        }

        $idTab = (int) Tab::getIdFromClassName('AdminRetractationDashboard');
        if ($idTab) {
            $tab = new Tab($idTab);
            $tab->delete();
        }

        Configuration::deleteByName('RETRACTATION_DELAY_DAYS');
        Configuration::deleteByName('RETRACTATION_BUFFER_SHIPPED');
        Configuration::deleteByName('RETRACTATION_BUFFER_ORDER');
        Configuration::deleteByName('RETRACTATION_ENABLED');
        Configuration::deleteByName('RETRACTATION_EMAIL_ENABLED');

        return parent::uninstall();
    }

    public function getContent()
    {
        if (Tools::isSubmit('submit_retractation2026')) {
            Configuration::updateValue('RETRACTATION_DELAY_DAYS', (int) Tools::getValue('RETRACTATION_DELAY_DAYS'));
            Configuration::updateValue('RETRACTATION_BUFFER_SHIPPED', (int) Tools::getValue('RETRACTATION_BUFFER_SHIPPED'));
            Configuration::updateValue('RETRACTATION_BUFFER_ORDER', (int) Tools::getValue('RETRACTATION_BUFFER_ORDER'));
            Configuration::updateValue('RETRACTATION_ENABLED', (bool) Tools::getValue('RETRACTATION_ENABLED'));
            Configuration::updateValue('RETRACTATION_EMAIL_ENABLED', (bool) Tools::getValue('RETRACTATION_EMAIL_ENABLED'));

            $this->context->controller->confirmations[] = $this->trans('Settings updated.', [], 'Modules.Retractation2026.Admin');
        }

        return $this->displayForm();
    }

    public function hookDisplayOrderDetail(array $params): string
    {
        if (empty($params['order']) || !Validate::isLoadedObject($params['order'])) {
            return '';
        }

        require_once dirname(__FILE__) . '/classes/RetractationEligibilityService.php';

        $service = new RetractationEligibilityService();
        $result = $service->getEligibility((int) $params['order']->id);

        if (!$result['eligible']) {
            return '';
        }

        $linkParams = ['id_order' => (int) $params['order']->id];

        if (!$this->context->customer->isLogged()) {
            $customer = new Customer((int) $params['order']->id_customer);
            if (Validate::isLoadedObject($customer)) {
                $linkParams['guest_email'] = $customer->email;
                $linkParams['order_reference'] = $params['order']->reference;
            }
        }

        $this->context->smarty->assign([
            'retractation_eligible' => true,
            'retractation_deadline' => $result['deadline'],
            'retractation_url' => $this->context->link->getModuleLink(
                'retractation2026',
                'request',
                $linkParams
            ),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayOrderDetail.tpl');
    }

    public function hookDisplayAdminOrderSide(array $params): string
    {
        $idOrder = (int) ($params['id_order'] ?? 0);

        if ($idOrder <= 0) {
            return '';
        }

        require_once dirname(__FILE__) . '/classes/RetractationEligibilityService.php';

        $service = new RetractationEligibilityService();
        $eligibility = $service->getEligibility($idOrder);

        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'retractation`
                WHERE `id_order` = ' . $idOrder . '
                    AND `id_shop` = ' . (int) Shop::getContextShopID() . '
                ORDER BY `date_add` DESC
                LIMIT 1';
        $request = Db::getInstance()->getRow($sql);

        $this->context->smarty->assign([
            'retractation_request' => $request ?: null,
            'retractation_eligibility' => $eligibility,
            'retractation_module_link' => $this->context->link->getAdminLink('AdminRetractationDashboard'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/admin_order_side.tpl');
    }

    public function hookDisplayCustomerAccount(array $params): string
    {
        $this->context->smarty->assign([
            'retractation_list_url' => $this->context->link->getModuleLink('retractation2026', 'retractationlist'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayCustomerAccount.tpl');
    }

    public function hookDisplayShoppingCartFooter(array $params): string
    {
        return $this->display(__FILE__, 'views/templates/hook/displayShoppingCartFooter.tpl');
    }

    public function hookDisplayFooter(array $params): string
    {
        $this->context->smarty->assign([
            'retractation_link' => $this->context->link->getModuleLink(
                $this->name,
                'request',
                [],
                true
            ),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayFooter.tpl');
    }

    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        return $this->display(__FILE__, 'views/templates/hook/displayProductAdditionalInfo.tpl');
    }

    private function displayForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->displayName,
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Délai légal de rétractation (jours)', [], 'Modules.Retractation2026.Admin'),
                        'name' => 'RETRACTATION_DELAY_DAYS',
                        'desc' => $this->trans('Nombre de jours calendaires du délai légal (14 par défaut)', [], 'Modules.Retractation2026.Admin'),
                        'cast' => 'intval',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Buffer expédition (jours)', [], 'Modules.Retractation2026.Admin'),
                        'name' => 'RETRACTATION_BUFFER_SHIPPED',
                        'desc' => $this->trans('Jours ajoutés quand seul le statut expédié est connu', [], 'Modules.Retractation2026.Admin'),
                        'cast' => 'intval',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Buffer commande (jours)', [], 'Modules.Retractation2026.Admin'),
                        'name' => 'RETRACTATION_BUFFER_ORDER',
                        'desc' => $this->trans('Jours ajoutés quand seule la date de commande est connue', [], 'Modules.Retractation2026.Admin'),
                        'cast' => 'intval',
                        'required' => true,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Module actif', [], 'Modules.Retractation2026.Admin'),
                        'name' => 'RETRACTATION_ENABLED',
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Oui', [], 'Modules.Retractation2026.Admin')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('Non', [], 'Modules.Retractation2026.Admin')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Envoyer un email de confirmation', [], 'Modules.Retractation2026.Admin'),
                        'name' => 'RETRACTATION_EMAIL_ENABLED',
                        'values' => [
                            ['id' => 'email_on', 'value' => 1, 'label' => $this->trans('Oui', [], 'Modules.Retractation2026.Admin')],
                            ['id' => 'email_off', 'value' => 0, 'label' => $this->trans('Non', [], 'Modules.Retractation2026.Admin')],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Enregistrer', [], 'Modules.Retractation2026.Admin'),
                    'name' => 'submit_retractation2026',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->identifier = $this->identifier;
        $helper->table = $this->table;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');

        $helper->fields_value['RETRACTATION_DELAY_DAYS'] = Configuration::get('RETRACTATION_DELAY_DAYS');
        $helper->fields_value['RETRACTATION_BUFFER_SHIPPED'] = Configuration::get('RETRACTATION_BUFFER_SHIPPED');
        $helper->fields_value['RETRACTATION_BUFFER_ORDER'] = Configuration::get('RETRACTATION_BUFFER_ORDER');
        $helper->fields_value['RETRACTATION_ENABLED'] = Configuration::get('RETRACTATION_ENABLED');
        $helper->fields_value['RETRACTATION_EMAIL_ENABLED'] = Configuration::get('RETRACTATION_EMAIL_ENABLED');

        return $helper->generateForm([$fields_form]);
    }

    private function executeSqlFile($filename)
    {
        $path = dirname(__FILE__) . '/sql/' . $filename . '.sql';

        if (!file_exists($path)) {
            return false;
        }

        $sql = file_get_contents($path);
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        $sql = str_replace('ENGINE_TYPE', _MYSQL_ENGINE_, $sql);

        return Db::getInstance()->execute($sql);
    }
}
