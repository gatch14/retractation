<?php
/**
 * @author    GSD
 * @copyright GSD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminRetractationDashboardController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = 'retractation';
        $this->identifier = 'id_retractation';
        $this->className = 'ObjectModel';
        $this->bootstrap = true;
        $this->lang = false;
        $this->addRowAction('view');
        $this->allow_export = true;

        $this->_select = 'o.`reference` AS `order_reference`, CONCAT(c.`firstname`, \' \', c.`lastname`) AS `customer_name`';
        $this->_join = 'LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.`id_order` = a.`id_order`) ';
        $this->_join .= 'LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (c.`id_customer` = a.`id_customer`)';
        $this->_where = ' AND a.`id_shop` = ' . (int) Shop::getContextShopID();

        $this->_defaultOrderBy = 'date_add';
        $this->_defaultOrderWay = 'DESC';

        $statusList = [
            'pending' => $this->l('Pending'),
            'accepted' => $this->l('Accepted'),
            'rejected' => $this->l('Rejected'),
            'cancelled' => $this->l('Cancelled'),
        ];

        $this->fields_list = [
            'id_retractation' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'order_reference' => [
                'title' => $this->l('Order'),
                'filter_key' => 'o!reference',
                'havingFilter' => false,
            ],
            'customer_name' => [
                'title' => $this->l('Customer'),
                'filter_key' => 'c!lastname',
                'havingFilter' => true,
            ],
            'status' => [
                'title' => $this->l('Status'),
                'type' => 'select',
                'list' => $statusList,
                'filter_key' => 'a!status',
                'callback' => 'getStatusBadge',
            ],
            'retractation_date' => [
                'title' => $this->l('Retractation date'),
                'type' => 'datetime',
                'filter_key' => 'a!retractation_date',
            ],
            'deadline_date' => [
                'title' => $this->l('Deadline'),
                'type' => 'datetime',
                'filter_key' => 'a!deadline_date',
            ],
            'deadline_source' => [
                'title' => $this->l('Source'),
                'filter_key' => 'a!deadline_source',
            ],
            'date_add' => [
                'title' => $this->l('Created'),
                'type' => 'datetime',
                'filter_key' => 'a!date_add',
            ],
        ];

        parent::__construct();
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
        $this->page_header_toolbar_title = $this->l('Retractation requests');
    }

    public function getStatusBadge($value)
    {
        $badges = [
            'pending' => 'badge-warning',
            'accepted' => 'badge-success',
            'rejected' => 'badge-danger',
            'cancelled' => 'badge-default',
        ];
        $class = isset($badges[$value]) ? $badges[$value] : 'badge-info';
        $label = ucfirst($value);

        return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
