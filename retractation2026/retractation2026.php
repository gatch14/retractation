<?php
/**
 * @author    Christophe Gatelet
 * @copyright Christophe Gatelet
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/autoload.php';

class Retractation2026 extends Module
{
    const CONFIG_KEYS = [
        'RETRACTATION_DELAY_DAYS',
        'RETRACTATION_BUFFER_SHIPPED',
        'RETRACTATION_BUFFER_ORDER',
        'RETRACTATION_ENABLED',
        'RETRACTATION_EMAIL_ENABLED',
        'RETRACTATION_SHOW_PRODUCT_NOTICE',
        'RETRACTATION_SHOW_CART_NOTICE',
        'RETRACTATION_PRODUCT_NOTICE_TEXT',
        'RETRACTATION_CART_NOTICE_TEXT',
        'RETRACTATION_ADMIN_EMAIL_ENABLED',
        'RETRACTATION_EXCLUDED_PRODUCTS',
        'RETRACTATION_EXCLUDED_CATEGORIES',
    ];

    const CONFIG_DEFAULTS = [
        'RETRACTATION_DELAY_DAYS' => 14,
        'RETRACTATION_BUFFER_SHIPPED' => 7,
        'RETRACTATION_BUFFER_ORDER' => 14,
        'RETRACTATION_ENABLED' => 1,
        'RETRACTATION_EMAIL_ENABLED' => 1,
        'RETRACTATION_SHOW_PRODUCT_NOTICE' => 1,
        'RETRACTATION_SHOW_CART_NOTICE' => 1,
        'RETRACTATION_PRODUCT_NOTICE_TEXT' => '',
        'RETRACTATION_CART_NOTICE_TEXT' => '',
        'RETRACTATION_ADMIN_EMAIL_ENABLED' => 1,
        'RETRACTATION_EXCLUDED_PRODUCTS' => '',
        'RETRACTATION_EXCLUDED_CATEGORIES' => '',
    ];

    const HOOKS = [
        'displayOrderDetail',
        'displayCustomerAccount',
        'displayAdminOrderSide',
        'displayProductAdditionalInfo',
        'displayShoppingCartFooter',
    ];

    public function __construct()
    {
        $this->name = 'retractation2026';
        $this->tab = 'legal_compliance';
        $this->version = '1.1.4';
        $this->author = 'Christophe Gatelet';
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

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install()
    {
        Shop::setContext(Shop::CONTEXT_ALL);

        if (!$this->executeSqlFile('install')) {
            return false;
        }

        $this->installTranslations();

        foreach (self::CONFIG_DEFAULTS as $key => $value) {
            Configuration::updateValue($key, $value);
        }
        Configuration::updateValue('RETRACTATION_PRODUCT_NOTICE_TEXT', '', true);
        Configuration::updateValue('RETRACTATION_CART_NOTICE_TEXT', '', true);

        $tab = new Tab();
        $tab->class_name = 'AdminRetractationDashboard';
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentOrders');
        $tab->module = $this->name;
        $tab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = ($lang['iso_code'] === 'fr') ? 'Rétractations' : 'Retractations';
        }
        if (!$tab->add()) {
            $this->cleanupInstall();
            return false;
        }

        $this->installMeta();

        $result = parent::install()
            && $this->registerHook('displayOrderDetail')
            && $this->registerHook('displayCustomerAccount')
            && $this->registerHook('displayAdminOrderSide')
            && $this->registerHook('displayProductAdditionalInfo')
            && $this->registerHook('displayShoppingCartFooter');

        if (!$result) {
            $this->cleanupInstall();
            return false;
        }

        return true;
    }

    private function cleanupInstall()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'retractation`');

        foreach (self::CONFIG_KEYS as $key) {
            Configuration::deleteByName($key);
        }

        $idTab = (int) Tab::getIdFromClassName('AdminRetractationDashboard');
        if ($idTab) {
            $tab = new Tab($idTab);
            $tab->delete();
        }

        $this->uninstallMeta();
    }

    private function installMeta()
    {
        $page = 'module-retractation2026-request';

        $idMeta = (int) Db::getInstance()->getValue(
            'SELECT id_meta FROM `' . _DB_PREFIX_ . 'meta` WHERE page = \'' . pSQL($page) . '\''
        );
        if ($idMeta) {
            return;
        }

        $meta = new Meta();
        $meta->page = $page;
        $meta->configurable = 0;
        $meta->title = [];
        $meta->description = [];
        $meta->url_rewrite = [];
        foreach (Language::getLanguages(true) as $lang) {
            $idLang = (int) $lang['id_lang'];
            $meta->title[$idLang] = 'Droit de rétractation';
            $meta->description[$idLang] = 'Exercer votre droit de rétractation';
            $meta->url_rewrite[$idLang] = 'retractation';
        }
        $meta->add();
    }

    private function uninstallMeta()
    {
        $page = 'module-retractation2026-request';
        $idMeta = (int) Db::getInstance()->getValue(
            'SELECT id_meta FROM `' . _DB_PREFIX_ . 'meta` WHERE page = \'' . pSQL($page) . '\''
        );
        if ($idMeta) {
            $meta = new Meta($idMeta);
            $meta->delete();
        }
    }

    public function uninstall()
    {
        // CR-08: archive records instead of dropping (legal evidentiary value)
        // Keep previous archives by renaming with a timestamp.
        $db = Db::getInstance();
        $table = '`' . _DB_PREFIX_ . 'retractation`';
        $exists = (bool) $db->getValue('SHOW TABLES LIKE \'' . pSQL(_DB_PREFIX_ . 'retractation') . '\'');
        if ($exists) {
            $archiveName = '`' . _DB_PREFIX_ . 'retractation_archived_' . date('YmdHis') . '`';
            $db->execute('RENAME TABLE ' . $table . ' TO ' . $archiveName);
        }

        $idTab = (int) Tab::getIdFromClassName('AdminRetractationDashboard');
        if ($idTab) {
            $tab = new Tab($idTab);
            $tab->delete();
        }

        $this->uninstallMeta();

        foreach (self::CONFIG_KEYS as $key) {
            Configuration::deleteByName($key);
        }

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
            Configuration::updateValue('RETRACTATION_ADMIN_EMAIL_ENABLED', (bool) Tools::getValue('RETRACTATION_ADMIN_EMAIL_ENABLED'));
            Configuration::updateValue('RETRACTATION_SHOW_PRODUCT_NOTICE', (bool) Tools::getValue('RETRACTATION_SHOW_PRODUCT_NOTICE'));
            Configuration::updateValue('RETRACTATION_SHOW_CART_NOTICE', (bool) Tools::getValue('RETRACTATION_SHOW_CART_NOTICE'));
            Configuration::updateValue('RETRACTATION_PRODUCT_NOTICE_TEXT', Tools::getValue('RETRACTATION_PRODUCT_NOTICE_TEXT'), true);
            Configuration::updateValue('RETRACTATION_CART_NOTICE_TEXT', Tools::getValue('RETRACTATION_CART_NOTICE_TEXT'), true);
            Configuration::updateValue('RETRACTATION_EXCLUDED_PRODUCTS', Tools::getValue('RETRACTATION_EXCLUDED_PRODUCTS'));
            Configuration::updateValue('RETRACTATION_EXCLUDED_CATEGORIES', Tools::getValue('RETRACTATION_EXCLUDED_CATEGORIES'));

            $this->context->controller->confirmations[] = $this->trans('Settings updated.', [], 'Modules.Retractation2026.Admin');
        }

        return $this->displayForm();
    }

    public function hookDisplayOrderDetail(array $params): string
    {
        if (empty($params['order']) || !Validate::isLoadedObject($params['order'])) {
            return '';
        }

        $service = new RetractationEligibilityService();
        $result = $service->getEligibility((int) $params['order']->id);

        if (!$result['eligible']) {
            return '';
        }

        // CR-02: guests link to lookup form — email must never appear in GET params
        if ($this->context->customer->isLogged()) {
            $linkParams = ['id_order' => (int) $params['order']->id];
        } else {
            $linkParams = [];
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
            'retractation_labels' => [
                'title' => $this->trans('Rétractation', [], 'Modules.Retractation2026.Admin'),
                'status' => $this->trans('Statut :', [], 'Modules.Retractation2026.Admin'),
                'pending' => $this->trans('En attente', [], 'Modules.Retractation2026.Admin'),
                'accepted' => $this->trans('Acceptée', [], 'Modules.Retractation2026.Admin'),
                'rejected' => $this->trans('Refusée', [], 'Modules.Retractation2026.Admin'),
                'retractation_date' => $this->trans('Date de rétractation :', [], 'Modules.Retractation2026.Admin'),
                'deadline' => $this->trans('Date limite :', [], 'Modules.Retractation2026.Admin'),
                'source' => $this->trans('Source :', [], 'Modules.Retractation2026.Admin'),
                'eligible' => $this->trans('Éligible', [], 'Modules.Retractation2026.Admin'),
                'not_eligible' => $this->trans('Non éligible', [], 'Modules.Retractation2026.Admin'),
                'reason' => $this->trans('Raison :', [], 'Modules.Retractation2026.Admin'),
                'dashboard' => $this->trans('Voir le tableau de bord', [], 'Modules.Retractation2026.Admin'),
            ],
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
        if (!(bool) Configuration::get('RETRACTATION_SHOW_CART_NOTICE')) {
            return '';
        }
        $this->context->smarty->assign([
            'retractation_cart_notice_text' => Configuration::get('RETRACTATION_CART_NOTICE_TEXT'),
            'retractation_form_url' => $this->context->link->getModuleLink($this->name, 'request'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayShoppingCartFooter.tpl');
    }

    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        if (!(bool) Configuration::get('RETRACTATION_SHOW_PRODUCT_NOTICE')) {
            return '';
        }

        $idProduct = (int) ($params['product']['id_product'] ?? 0);
        $isVirtual = !empty($params['product']['is_virtual']);
        $isExcluded = $idProduct > 0 && $this->isProductExcluded($idProduct);

        $this->context->smarty->assign([
            'retractation_product_eligible' => !$isVirtual && !$isExcluded,
            'retractation_product_notice_text' => Configuration::get('RETRACTATION_PRODUCT_NOTICE_TEXT'),
            'retractation_form_url' => $this->context->link->getModuleLink($this->name, 'request'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayProductAdditionalInfo.tpl');
    }

    private function isProductExcluded(int $idProduct): bool
    {
        $excludedProducts = array_filter(array_map('intval', explode(',', (string) Configuration::get('RETRACTATION_EXCLUDED_PRODUCTS'))));
        if (in_array($idProduct, $excludedProducts, true)) {
            return true;
        }

        $excludedCategories = array_filter(array_map('intval', explode(',', (string) Configuration::get('RETRACTATION_EXCLUDED_CATEGORIES'))));
        if (empty($excludedCategories)) {
            return false;
        }

        return (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'category_product`
             WHERE `id_product` = ' . $idProduct . '
               AND `id_category` IN (' . implode(',', $excludedCategories) . ')
             LIMIT 1'
        );
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
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Notifier le marchand par email', [], 'Modules.Retractation2026.Admin'),
                        'name' => 'RETRACTATION_ADMIN_EMAIL_ENABLED',
                        'desc' => $this->trans('Envoyer un email de notification à l\'adresse de la boutique lors d\'une nouvelle demande.', [], 'Modules.Retractation2026.Admin'),
                        'values' => [
                            ['id' => 'admin_email_on', 'value' => 1, 'label' => $this->trans('Oui', [], 'Modules.Retractation2026.Admin')],
                            ['id' => 'admin_email_off', 'value' => 0, 'label' => $this->trans('Non', [], 'Modules.Retractation2026.Admin')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Afficher la notice sur les fiches produit', [], 'Modules.Retractation2026.Admin'),
                        'name' => 'RETRACTATION_SHOW_PRODUCT_NOTICE',
                        'desc' => $this->trans('Masquer pour les produits dématérialisés ou si vous gérez la notice via le thème', [], 'Modules.Retractation2026.Admin'),
                        'values' => [
                            ['id' => 'pnotice_on', 'value' => 1, 'label' => $this->trans('Oui', [], 'Modules.Retractation2026.Admin')],
                            ['id' => 'pnotice_off', 'value' => 0, 'label' => $this->trans('Non', [], 'Modules.Retractation2026.Admin')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Afficher la notice dans le panier', [], 'Modules.Retractation2026.Admin'),
                        'name' => 'RETRACTATION_SHOW_CART_NOTICE',
                        'values' => [
                            ['id' => 'cnotice_on', 'value' => 1, 'label' => $this->trans('Oui', [], 'Modules.Retractation2026.Admin')],
                            ['id' => 'cnotice_off', 'value' => 0, 'label' => $this->trans('Non', [], 'Modules.Retractation2026.Admin')],
                        ],
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->trans('Texte personnalisé — fiche produit', [], 'Modules.Retractation2026.Admin'),
                        'name' => 'RETRACTATION_PRODUCT_NOTICE_TEXT',
                        'desc' => $this->trans('Laissez vide pour afficher le texte par défaut du module', [], 'Modules.Retractation2026.Admin'),
                        'autoload_rte' => true,
                        'cols' => 60,
                        'rows' => 6,
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->trans('Texte personnalisé — panier', [], 'Modules.Retractation2026.Admin'),
                        'name' => 'RETRACTATION_CART_NOTICE_TEXT',
                        'desc' => $this->trans('Laissez vide pour afficher le texte par défaut du module', [], 'Modules.Retractation2026.Admin'),
                        'autoload_rte' => true,
                        'cols' => 60,
                        'rows' => 6,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Produits exclus du droit de rétractation', [], 'Modules.Retractation2026.Admin'),
                        'name' => 'RETRACTATION_EXCLUDED_PRODUCTS',
                        'desc' => $this->trans('IDs de produits séparés par des virgules (article L.221-28).', [], 'Modules.Retractation2026.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Catégories exclues du droit de rétractation', [], 'Modules.Retractation2026.Admin'),
                        'name' => 'RETRACTATION_EXCLUDED_CATEGORIES',
                        'desc' => $this->trans('IDs de catégories séparés par des virgules (article L.221-28).', [], 'Modules.Retractation2026.Admin'),
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
        $helper->fields_value['RETRACTATION_ADMIN_EMAIL_ENABLED'] = Configuration::get('RETRACTATION_ADMIN_EMAIL_ENABLED');
        $helper->fields_value['RETRACTATION_SHOW_PRODUCT_NOTICE'] = Configuration::get('RETRACTATION_SHOW_PRODUCT_NOTICE');
        $helper->fields_value['RETRACTATION_SHOW_CART_NOTICE'] = Configuration::get('RETRACTATION_SHOW_CART_NOTICE');
        $helper->fields_value['RETRACTATION_PRODUCT_NOTICE_TEXT'] = Configuration::get('RETRACTATION_PRODUCT_NOTICE_TEXT');
        $helper->fields_value['RETRACTATION_CART_NOTICE_TEXT'] = Configuration::get('RETRACTATION_CART_NOTICE_TEXT');
        $helper->fields_value['RETRACTATION_EXCLUDED_PRODUCTS'] = Configuration::get('RETRACTATION_EXCLUDED_PRODUCTS');
        $helper->fields_value['RETRACTATION_EXCLUDED_CATEGORIES'] = Configuration::get('RETRACTATION_EXCLUDED_CATEGORIES');

        return $helper->generateForm([$fields_form]);
    }

    public function installTranslations()
    {
        $idLang = (int) Language::getIdByIso('fr');
        if (!$idLang) {
            return;
        }

        $db = Db::getInstance();
        $domains = [
            'Modules.Retractation2026.Shop',
            'Modules.Retractation2026.Front',
            'Modules.Retractation2026.Admin',
            'ModulesRetractation2026Front',
            'ModulesRetractation2026Admin',
        ];
        foreach ($domains as $domain) {
            $db->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'translation`
                 WHERE domain = \'' . pSQL($domain) . '\' AND id_lang = ' . $idLang
            );
        }

        $front = [
            'My retractation requests' => 'Mes demandes de rétractation',
            'Order Reference' => 'Référence de commande',
            'Status' => 'Statut',
            'Retractation Date' => 'Date de rétractation',
            'Deadline Date' => 'Date limite',
            'Pending' => 'En attente',
            'Accepted' => 'Acceptée',
            'Rejected' => 'Refusée',
            'Cancelled' => 'Annulée',
            'You have no retractation requests.' => 'Vous n\'avez aucune demande de rétractation.',
            'Back to my account' => 'Retour à mon compte',
            'Legal information' => 'Informations légales',
            'Right of withdrawal' => 'Droit de rétractation',
            'This product is eligible for the legal right of withdrawal.' => 'Ce produit est éligible au droit légal de rétractation.',
            'You may return it within the statutory deadline after delivery.' => 'Vous pouvez le retourner dans le délai légal suivant la livraison.',
            'In accordance with applicable consumer protection law, you have a right of withdrawal that you may exercise within the legal deadline after receiving your order.' => 'Conformément au droit de la consommation applicable, vous disposez d\'un droit de rétractation que vous pouvez exercer dans le délai légal suivant la réception de votre commande.',
            'You can submit your withdrawal request from the "My retractations" section of your customer account.' => 'Vous pouvez soumettre votre demande de rétractation depuis la rubrique « Mes rétractations » de votre espace client.',
            'You can submit your withdrawal request from our online withdrawal form.' => 'Vous pouvez soumettre votre demande de rétractation depuis notre formulaire de rétractation en ligne.',
            'Demande de rétractation' => 'Demande de rétractation',
            'Pour exercer votre droit de rétractation, veuillez renseigner votre adresse email et la référence de votre commande.' => 'Pour exercer votre droit de rétractation, veuillez renseigner votre adresse email et la référence de votre commande.',
            'Adresse email' => 'Adresse email',
            'Email utilisé lors de la commande' => 'Email utilisé lors de la commande',
            'Référence de commande' => 'Référence de commande',
            'Ex: ABCDEFGH' => 'Ex : ABCDEFGH',
            'Rechercher ma commande' => 'Rechercher ma commande',
            'Date limite de rétractation :' => 'Date limite de rétractation :',
            'Prénom' => 'Prénom',
            'Nom' => 'Nom',
            'Email' => 'Email',
            'Motif (facultatif)' => 'Motif (facultatif)',
            'Indiquez le motif de votre rétractation si vous le souhaitez' => 'Indiquez le motif de votre rétractation si vous le souhaitez',
            'Confirmer la rétractation' => 'Confirmer la rétractation',
            'Vous pouvez exercer votre droit de rétractation jusqu\'au' => 'Vous pouvez exercer votre droit de rétractation jusqu\'au',
            'Renoncer au contrat ici' => 'Renoncer au contrat ici',
            'Confirmation de rétractation' => 'Confirmation de rétractation',
            'Votre demande de rétractation a été enregistrée avec succès.' => 'Votre demande de rétractation a été enregistrée avec succès.',
            'Référence de commande :' => 'Référence de commande :',
            'Date de rétractation :' => 'Date de rétractation :',
            'Heure de rétractation :' => 'Heure de rétractation :',
            'Un email de confirmation vous sera envoyé.' => 'Un email de confirmation vous sera envoyé.',
            'Retour au suivi de commande' => 'Retour au suivi de commande',
            'Retour à mes commandes' => 'Retour à mes commandes',
            'Exercer mon droit de rétractation' => 'Exercer mon droit de rétractation',
            'This order is not eligible for retractation.' => 'Cette commande n\'est pas éligible à la rétractation.',
            'Please provide your email and order reference.' => 'Veuillez renseigner votre adresse email et la référence de commande.',
            'Invalid email address.' => 'Adresse email invalide.',
            'No order found with this reference and email.' => 'Aucune commande trouvée avec cette référence et cet email.',
            'Invalid order.' => 'Commande invalide.',
            'Order not found.' => 'Commande introuvable.',
            'You do not have permission to access this order.' => 'Vous n\'êtes pas autorisé à accéder à cette commande.',
            'Invalid security token. Please try again.' => 'Jeton de sécurité invalide. Veuillez réessayer.',
            'An error occurred while processing your request. Please try again.' => 'Une erreur est survenue lors du traitement de votre demande. Veuillez réessayer.',
            'A retractation request already exists for this order.' => 'Une demande de rétractation existe déjà pour cette commande.',
            'This order does not belong to your account.' => 'Cette commande n\'appartient pas à votre compte.',
            'Confirmation of your retractation — Order %s' => 'Confirmation de votre rétractation — Commande %s',
            'New retractation request — Order %s' => 'Nouvelle demande de rétractation — Commande %s',
            'Withdrawal form' => 'Formulaire de rétractation',
            'In accordance with Article L.221-18 of the French Consumer Code, you have a right of withdrawal of 14 calendar days from receipt of this product.' => 'Conformément à l\'article L.221-18 du Code de la consommation, vous disposez d\'un droit de rétractation de 14 jours calendaires à compter de la réception de ce produit.',
            'This product is excluded from the right of withdrawal in accordance with Article L.221-28 of the French Consumer Code.' => 'Ce produit est exclu du droit de rétractation conformément à l\'article L.221-28 du Code de la consommation.',
            'In accordance with Article L.221-18 of the French Consumer Code, you have a right of withdrawal of 14 calendar days from receipt of your order. If the deadline expires on a Saturday, Sunday or public holiday, it is extended to the next working day.' => 'Conformément à l\'article L.221-18 du Code de la consommation, vous disposez d\'un droit de rétractation de 14 jours calendaires à compter de la réception de votre commande. Si le délai expire un samedi, dimanche ou jour férié, il est prolongé jusqu\'au premier jour ouvrable suivant.',
        ];

        $admin = [
            'Rétractation 2026' => 'Rétractation 2026',
            'Settings updated.' => 'Paramètres enregistrés.',
            'ID' => 'ID',
            'Order' => 'Commande',
            'Customer' => 'Client',
            'Status' => 'Statut',
            'Retractation date' => 'Date de rétractation',
            'Deadline' => 'Date limite',
            'Source' => 'Source',
            'Created' => 'Créée le',
            'Accept' => 'Accepter',
            'Reject' => 'Refuser',
            'Pending' => 'En attente',
            'Accepted' => 'Acceptée',
            'Rejected' => 'Refusée',
            'Cancelled' => 'Annulée',
            'Status updated.' => 'Statut mis à jour.',
            'Délai légal de rétractation (jours)' => 'Délai légal de rétractation (jours)',
            'Nombre de jours calendaires du délai légal (14 par défaut)' => 'Nombre de jours calendaires du délai légal (14 par défaut)',
            'Buffer expédition (jours)' => 'Buffer expédition (jours)',
            'Jours ajoutés quand seul le statut expédié est connu' => 'Jours ajoutés quand seul le statut expédié est connu',
            'Buffer commande (jours)' => 'Buffer commande (jours)',
            'Jours ajoutés quand seule la date de commande est connue' => 'Jours ajoutés quand seule la date de commande est connue',
            'Module actif' => 'Module actif',
            'Oui' => 'Oui',
            'Non' => 'Non',
            'Envoyer un email de confirmation' => 'Envoyer un email de confirmation',
            'Afficher la notice sur les fiches produit' => 'Afficher la notice sur les fiches produit',
            'Masquer pour les produits dématérialisés ou si vous gérez la notice via le thème' => 'Masquer pour les produits dématérialisés ou si vous gérez la notice via le thème',
            'Afficher la notice dans le panier' => 'Afficher la notice dans le panier',
            'Texte personnalisé — fiche produit' => 'Texte personnalisé — fiche produit',
            'Laissez vide pour afficher le texte par défaut du module' => 'Laissez vide pour afficher le texte par défaut du module',
            'Texte personnalisé — panier' => 'Texte personnalisé — panier',
            'Enregistrer' => 'Enregistrer',
            'Êtes-vous sûr de vouloir désinstaller ce module ? Toutes les données de rétractation seront perdues.' => 'Êtes-vous sûr de vouloir désinstaller ce module ? Toutes les données de rétractation seront perdues.',
            'Rejection reason (required)' => 'Motif de refus (obligatoire)',
            'Enter the reason for rejection...' => 'Saisissez le motif de refus...',
            'Please enter a rejection reason.' => 'Veuillez saisir un motif de refus.',
            'Rejection reason' => 'Motif de refus',
            'Customer reason' => 'Motif du client',
            'Back to list' => 'Retour à la liste',
            'Retractation not found.' => 'Rétractation non trouvée.',
            'Retractation requests' => 'Demandes de rétractation',
            'Invalid status.' => 'Statut invalide.',
            'Could not update status.' => 'Impossible de mettre à jour le statut.',
            'Your retractation has been accepted' => 'Votre demande de rétractation a été acceptée',
            'Your retractation has been rejected' => 'Votre demande de rétractation a été refusée',
            'Email notification could not be sent.' => 'L\'email de notification n\'a pas pu être envoyé.',
            'Notifier le marchand par email' => 'Notifier le marchand par email',
            'Envoyer un email de notification à l\'adresse de la boutique lors d\'une nouvelle demande.' => 'Envoyer un email de notification à l\'adresse de la boutique lors d\'une nouvelle demande.',
            'Rétractation' => 'Rétractation',
            'Statut :' => 'Statut :',
            'En attente' => 'En attente',
            'Acceptée' => 'Acceptée',
            'Refusée' => 'Refusée',
            'Date de rétractation :' => 'Date de rétractation :',
            'Date limite :' => 'Date limite :',
            'Source :' => 'Source :',
            'Éligible' => 'Éligible',
            'Non éligible' => 'Non éligible',
            'Raison :' => 'Raison :',
            'Voir le tableau de bord' => 'Voir le tableau de bord',
            'Produits exclus du droit de rétractation' => 'Produits exclus du droit de rétractation',
            'IDs de produits séparés par des virgules (article L.221-28).' => 'IDs de produits séparés par des virgules (article L.221-28).',
            'Catégories exclues du droit de rétractation' => 'Catégories exclues du droit de rétractation',
            'IDs de catégories séparés par des virgules (article L.221-28).' => 'IDs de catégories séparés par des virgules (article L.221-28).',
        ];

        foreach (['Modules.Retractation2026.Shop' => $front, 'Modules.Retractation2026.Admin' => $admin] as $domain => $strings) {
            foreach ($strings as $key => $translation) {
                $db->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'translation` (id_lang, `key`, translation, domain, theme)
                     VALUES (' . $idLang . ', \'' . pSQL($key) . '\', \'' . pSQL($translation) . '\', \'' . pSQL($domain) . '\', NULL)'
                );
            }
        }
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

        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if (empty($statement)) {
                continue;
            }
            if (!Db::getInstance()->execute($statement)) {
                return false;
            }
        }

        return true;
    }
}
