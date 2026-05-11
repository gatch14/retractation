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
        'RETRACTATION_SHOW_PRODUCT_NOTICE',
        'RETRACTATION_SHOW_CART_NOTICE',
        'RETRACTATION_PRODUCT_NOTICE_TEXT',
        'RETRACTATION_CART_NOTICE_TEXT',
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
    ];

    const HOOKS = [
        'displayOrderDetail',
        'displayCustomerAccount',
        'displayAdminOrderSide',
        'displayProductAdditionalInfo',
        'displayShoppingCartFooter',
        'displayHeader',
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

        $this->installTranslations();

        Configuration::updateValue('RETRACTATION_DELAY_DAYS', 14);
        Configuration::updateValue('RETRACTATION_BUFFER_SHIPPED', 7);
        Configuration::updateValue('RETRACTATION_BUFFER_ORDER', 14);
        Configuration::updateValue('RETRACTATION_ENABLED', 1);
        Configuration::updateValue('RETRACTATION_EMAIL_ENABLED', 1);
        Configuration::updateValue('RETRACTATION_SHOW_PRODUCT_NOTICE', 1);
        Configuration::updateValue('RETRACTATION_SHOW_CART_NOTICE', 1);
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
            return false;
        }

        $this->installMeta();
        $this->installFooterLink();

        return parent::install()
            && $this->registerHook('displayOrderDetail')
            && $this->registerHook('displayCustomerAccount')
            && $this->registerHook('displayAdminOrderSide')
            && $this->registerHook('displayProductAdditionalInfo')
            && $this->registerHook('displayShoppingCartFooter')
            && $this->registerHook('displayHeader');
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

    private function installFooterLink()
    {
        $url = $this->context->link->getModuleLink($this->name, 'request', [], true);
        $db = Db::getInstance();

        $idBlock = (int) $db->getValue(
            'SELECT lb.id_link_block FROM `' . _DB_PREFIX_ . 'link_block` lb
            JOIN `' . _DB_PREFIX_ . 'link_block_lang` lbl ON lb.id_link_block = lbl.id_link_block
            WHERE lb.id_hook = (SELECT id_hook FROM `' . _DB_PREFIX_ . 'hook` WHERE name = \'displayFooter\' LIMIT 1)
            ORDER BY lb.position DESC LIMIT 1'
        );

        if (!$idBlock) {
            return;
        }

        $languages = Language::getLanguages(true);
        foreach ($languages as $lang) {
            $idLang = (int) $lang['id_lang'];
            $title = ($lang['iso_code'] === 'fr') ? 'Droit de rétractation' : 'Right of withdrawal';

            $existing = $db->getValue(
                'SELECT custom_content FROM `' . _DB_PREFIX_ . 'link_block_lang`
                WHERE id_link_block = ' . $idBlock . ' AND id_lang = ' . $idLang
            );

            $links = [];
            if ($existing) {
                $decoded = json_decode($existing, true);
                if (is_array($decoded)) {
                    $links = $decoded;
                }
            }

            foreach ($links as $link) {
                if (isset($link['url']) && strpos($link['url'], 'retractation') !== false) {
                    continue 2;
                }
            }

            $links[] = ['title' => $title, 'url' => $url];

            $db->execute(
                'UPDATE `' . _DB_PREFIX_ . 'link_block_lang`
                SET custom_content = \'' . pSQL(json_encode($links)) . '\'
                WHERE id_link_block = ' . $idBlock . ' AND id_lang = ' . $idLang
            );
        }
    }

    private function uninstallFooterLink()
    {
        $db = Db::getInstance();

        $rows = $db->executeS(
            'SELECT id_link_block, id_lang, custom_content FROM `' . _DB_PREFIX_ . 'link_block_lang`
            WHERE custom_content IS NOT NULL AND custom_content != \'\''
        );

        if (!$rows) {
            return;
        }

        foreach ($rows as $row) {
            $links = json_decode($row['custom_content'], true);
            if (!is_array($links)) {
                continue;
            }

            $filtered = array_values(array_filter($links, function ($link) {
                return !isset($link['url']) || strpos($link['url'], 'retractation') === false;
            }));

            $value = empty($filtered) ? 'NULL' : '\'' . pSQL(json_encode($filtered)) . '\'';
            $db->execute(
                'UPDATE `' . _DB_PREFIX_ . 'link_block_lang`
                SET custom_content = ' . $value . '
                WHERE id_link_block = ' . (int) $row['id_link_block'] . '
                AND id_lang = ' . (int) $row['id_lang']
            );
        }
    }

    public function uninstall()
    {
        // CR-08: archive records instead of dropping (legal evidentiary value)
        $db = Db::getInstance();
        $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'retractation_archived`');
        $db->execute('RENAME TABLE `' . _DB_PREFIX_ . 'retractation` TO `' . _DB_PREFIX_ . 'retractation_archived`');

        $idTab = (int) Tab::getIdFromClassName('AdminRetractationDashboard');
        if ($idTab) {
            $tab = new Tab($idTab);
            $tab->delete();
        }

        $this->uninstallMeta();
        $this->uninstallFooterLink();

        Configuration::deleteByName('RETRACTATION_DELAY_DAYS');
        Configuration::deleteByName('RETRACTATION_BUFFER_SHIPPED');
        Configuration::deleteByName('RETRACTATION_BUFFER_ORDER');
        Configuration::deleteByName('RETRACTATION_ENABLED');
        Configuration::deleteByName('RETRACTATION_EMAIL_ENABLED');
        Configuration::deleteByName('RETRACTATION_SHOW_PRODUCT_NOTICE');
        Configuration::deleteByName('RETRACTATION_SHOW_CART_NOTICE');
        Configuration::deleteByName('RETRACTATION_PRODUCT_NOTICE_TEXT');
        Configuration::deleteByName('RETRACTATION_CART_NOTICE_TEXT');

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
            Configuration::updateValue('RETRACTATION_SHOW_PRODUCT_NOTICE', (bool) Tools::getValue('RETRACTATION_SHOW_PRODUCT_NOTICE'));
            Configuration::updateValue('RETRACTATION_SHOW_CART_NOTICE', (bool) Tools::getValue('RETRACTATION_SHOW_CART_NOTICE'));
            Configuration::updateValue('RETRACTATION_PRODUCT_NOTICE_TEXT', Tools::getValue('RETRACTATION_PRODUCT_NOTICE_TEXT'), true);
            Configuration::updateValue('RETRACTATION_CART_NOTICE_TEXT', Tools::getValue('RETRACTATION_CART_NOTICE_TEXT'), true);

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
        if (!(bool) Configuration::get('RETRACTATION_SHOW_CART_NOTICE')) {
            return '';
        }
        $this->context->smarty->assign([
            'retractation_cart_notice_text' => Configuration::get('RETRACTATION_CART_NOTICE_TEXT'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayShoppingCartFooter.tpl');
    }

    public function hookDisplayProductAdditionalInfo(array $params): string
    {
        // IN-03/CR-06: do not show withdrawal notice for virtual/downloadable products (L221-28)
        if (!empty($params['product']['is_virtual'])) {
            return '';
        }
        if (!(bool) Configuration::get('RETRACTATION_SHOW_PRODUCT_NOTICE')) {
            return '';
        }
        $this->context->smarty->assign([
            'retractation_product_notice_text' => Configuration::get('RETRACTATION_PRODUCT_NOTICE_TEXT'),
        ]);

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
        $helper->fields_value['RETRACTATION_SHOW_PRODUCT_NOTICE'] = Configuration::get('RETRACTATION_SHOW_PRODUCT_NOTICE');
        $helper->fields_value['RETRACTATION_SHOW_CART_NOTICE'] = Configuration::get('RETRACTATION_SHOW_CART_NOTICE');
        $helper->fields_value['RETRACTATION_PRODUCT_NOTICE_TEXT'] = Configuration::get('RETRACTATION_PRODUCT_NOTICE_TEXT');
        $helper->fields_value['RETRACTATION_CART_NOTICE_TEXT'] = Configuration::get('RETRACTATION_CART_NOTICE_TEXT');

        return $helper->generateForm([$fields_form]);
    }

    private function installTranslations()
    {
        $idLang = (int) Language::getIdByIso('fr');
        if (!$idLang) {
            return;
        }

        $db = Db::getInstance();
        $domains = ['ModulesRetractation2026Front', 'ModulesRetractation2026Admin'];
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
        ];

        $admin = [
            'Rétractation 2026' => 'Rétractation 2026',
            'Settings updated.' => 'Paramètres enregistrés.',
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
        ];

        foreach (['ModulesRetractation2026Front' => $front, 'ModulesRetractation2026Admin' => $admin] as $domain => $strings) {
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

        return Db::getInstance()->execute($sql);
    }
}
