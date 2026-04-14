<?php
/**
 * @author    GSD
 * @copyright GSD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'retractation2026/classes/Retractation.php';

class AdminRetractationDashboardController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = 'retractation';
        $this->identifier = 'id_retractation';
        $this->className = 'Retractation';
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

        parent::__construct();

        $statusList = [
            'pending' => $this->module->l('Pending', 'AdminRetractationDashboardController'),
            'accepted' => $this->module->l('Accepted', 'AdminRetractationDashboardController'),
            'rejected' => $this->module->l('Rejected', 'AdminRetractationDashboardController'),
            'cancelled' => $this->module->l('Cancelled', 'AdminRetractationDashboardController'),
        ];

        $this->fields_list = [
            'id_retractation' => [
                'title' => $this->module->l('ID', 'AdminRetractationDashboardController'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'order_reference' => [
                'title' => $this->module->l('Order', 'AdminRetractationDashboardController'),
                'filter_key' => 'o!reference',
                'havingFilter' => false,
            ],
            'customer_name' => [
                'title' => $this->module->l('Customer', 'AdminRetractationDashboardController'),
                'filter_key' => 'c!lastname',
                'havingFilter' => true,
            ],
            'status' => [
                'title' => $this->module->l('Status', 'AdminRetractationDashboardController'),
                'type' => 'select',
                'list' => $statusList,
                'filter_key' => 'a!status',
                'callback' => 'getStatusBadge',
            ],
            'retractation_date' => [
                'title' => $this->module->l('Retractation date', 'AdminRetractationDashboardController'),
                'type' => 'datetime',
                'filter_key' => 'a!retractation_date',
            ],
            'deadline_date' => [
                'title' => $this->module->l('Deadline', 'AdminRetractationDashboardController'),
                'type' => 'datetime',
                'filter_key' => 'a!deadline_date',
            ],
            'deadline_source' => [
                'title' => $this->module->l('Source', 'AdminRetractationDashboardController'),
                'filter_key' => 'a!deadline_source',
            ],
            'date_add' => [
                'title' => $this->module->l('Created', 'AdminRetractationDashboardController'),
                'type' => 'datetime',
                'filter_key' => 'a!date_add',
            ],
        ];
    }

    public function renderView()
    {
        $id = (int) Tools::getValue('id_retractation');
        $sql = new DbQuery();
        $sql->select('r.*, o.`reference` AS `order_reference`, CONCAT(c.`firstname`, \' \', c.`lastname`) AS `customer_name`, c.`email`');
        $sql->from('retractation', 'r');
        $sql->leftJoin('orders', 'o', 'o.`id_order` = r.`id_order`');
        $sql->leftJoin('customer', 'c', 'c.`id_customer` = r.`id_customer`');
        $sql->where('r.`id_retractation` = ' . $id);
        $sql->where('r.`id_shop` = ' . (int) Shop::getContextShopID());

        $row = Db::getInstance()->getRow($sql);
        if (!$row) {
            $this->errors[] = $this->module->l('Retractation not found.', 'AdminRetractationDashboardController');
            return parent::renderList();
        }

        $statusActions = '';
        if ($row['status'] === 'pending') {
            $acceptUrl = $this->context->link->getAdminLink('AdminRetractationDashboard') . '&id_retractation=' . $id . '&statusretractation&new_status=accepted';
            $rejectUrl = $this->context->link->getAdminLink('AdminRetractationDashboard') . '&id_retractation=' . $id . '&statusretractation&new_status=rejected';
            $statusActions = '<a href="' . $acceptUrl . '" class="btn btn-success"><i class="icon-check"></i> ' . $this->module->l('Accept', 'AdminRetractationDashboardController') . '</a> ';
            $statusActions .= '<a href="' . $rejectUrl . '" class="btn btn-danger"><i class="icon-remove"></i> ' . $this->module->l('Reject', 'AdminRetractationDashboardController') . '</a>';
        }

        $html = '<div class="panel">';
        $html .= '<div class="panel-heading"><i class="icon-eye"></i> ' . $this->module->l('Retractation', 'AdminRetractationDashboardController') . ' #' . $id . '</div>';
        $html .= '<div class="row">';
        $html .= '<div class="col-lg-6">';
        $html .= '<table class="table">';
        $orderLink = $this->context->link->getAdminLink('AdminOrders', true, [], ['id_order' => (int)$row['id_order'], 'vieworder' => 1]);
        $html .= '<tr><td><strong>' . $this->module->l('Order', 'AdminRetractationDashboardController') . '</strong></td><td><a href="' . $orderLink . '">' . htmlspecialchars($row['order_reference'], ENT_QUOTES, 'UTF-8') . ' <i class="icon-external-link"></i></a></td></tr>';
        $html .= '<tr><td><strong>' . $this->module->l('Customer', 'AdminRetractationDashboardController') . '</strong></td><td>' . htmlspecialchars($row['customer_name'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->module->l('Email', 'AdminRetractationDashboardController') . '</strong></td><td>' . htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->module->l('Status', 'AdminRetractationDashboardController') . '</strong></td><td>' . $this->getStatusBadge($row['status']) . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->module->l('Retractation date', 'AdminRetractationDashboardController') . '</strong></td><td>' . htmlspecialchars($row['retractation_date'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->module->l('Deadline', 'AdminRetractationDashboardController') . '</strong></td><td>' . htmlspecialchars($row['deadline_date'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->module->l('Source', 'AdminRetractationDashboardController') . '</strong></td><td>' . htmlspecialchars($row['deadline_source'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->module->l('IP', 'AdminRetractationDashboardController') . '</strong></td><td>' . htmlspecialchars($row['ip_address'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->module->l('Created', 'AdminRetractationDashboardController') . '</strong></td><td>' . htmlspecialchars($row['date_add'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        $html .= '<div class="col-lg-6">';
        $html .= '<div class="panel"><div class="panel-heading">' . $this->module->l('Reason', 'AdminRetractationDashboardController') . '</div>';
        $html .= '<p>' . nl2br(htmlspecialchars($row['reason'], ENT_QUOTES, 'UTF-8')) . '</p>';
        $html .= '</div>';
        if ($statusActions) {
            $html .= '<div class="panel"><div class="panel-heading">' . $this->module->l('Actions', 'AdminRetractationDashboardController') . '</div>';
            $html .= $statusActions;
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        $backUrl = $this->context->link->getAdminLink('AdminRetractationDashboard');
        $html .= '<a href="' . $backUrl . '" class="btn btn-default"><i class="icon-arrow-left"></i> ' . $this->module->l('Back to list', 'AdminRetractationDashboardController') . '</a>';

        return $html;
    }

    public function postProcess()
    {
        if (Tools::getIsset('statusretractation') && Tools::getValue('id_retractation') && Tools::getValue('new_status')) {
            $id = (int) Tools::getValue('id_retractation');
            $newStatus = pSQL(Tools::getValue('new_status'));
            $allowed = ['accepted', 'rejected', 'cancelled'];
            if (!in_array($newStatus, $allowed)) {
                $this->errors[] = $this->module->l('Invalid status.', 'AdminRetractationDashboardController');
                return;
            }
            $result = Db::getInstance()->update('retractation', [
                'status' => $newStatus,
                'date_upd' => date('Y-m-d H:i:s'),
            ], 'id_retractation = ' . $id . ' AND id_shop = ' . (int) Shop::getContextShopID());

            if ($result) {
                $this->sendStatusEmail($id, $newStatus);
                $this->confirmations[] = $this->module->l('Status updated.', 'AdminRetractationDashboardController');
            } else {
                $this->errors[] = $this->module->l('Could not update status.', 'AdminRetractationDashboardController');
            }
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminRetractationDashboard') . '&viewretractation&id_retractation=' . $id);
        }
        return parent::postProcess();
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
        $this->page_header_toolbar_title = $this->module->l('Retractation requests', 'AdminRetractationDashboardController');
    }

    private function sendStatusEmail($idRetractation, $status)
    {
        if (!in_array($status, ['accepted', 'rejected', 'cancelled'])) {
            return;
        }

        $row = Db::getInstance()->getRow(
            'SELECT r.*, o.reference as order_reference, c.firstname, c.lastname, c.email as customer_email
             FROM `' . _DB_PREFIX_ . 'retractation` r
             LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order = r.id_order
             LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = r.id_customer
             WHERE r.id_retractation = ' . (int) $idRetractation
        );
        if (!$row) {
            return;
        }

        $templateVars = [
            '{firstname}' => $row['firstname'],
            '{lastname}' => $row['lastname'],
            '{order_reference}' => $row['order_reference'],
            '{retractation_date}' => date('d/m/Y', strtotime($row['date_add'])),
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
            '{shop_url}' => Context::getContext()->link->getBaseLink(),
            '{reject_reason}' => $status === 'rejected'
                ? $this->module->l('Your request does not meet the eligibility criteria.', 'AdminRetractationDashboardController')
                : '',
        ];

        $emailStatus = ($status === 'cancelled') ? 'rejected' : $status;
        $template = 'retractation_' . $emailStatus;
        $subject = $emailStatus === 'accepted'
            ? $this->module->l('Your retractation has been accepted', 'AdminRetractationDashboardController')
            : $this->module->l('Your retractation has been rejected', 'AdminRetractationDashboardController');

        @Mail::Send(
            (int) Context::getContext()->language->id,
            $template,
            $subject,
            $templateVars,
            $row['customer_email'],
            $row['firstname'] . ' ' . $row['lastname'],
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'retractation2026/mails/'
        );
    }

    public function getStatusBadge($value)
    {
        $badges = [
            'pending' => 'badge-warning',
            'accepted' => 'badge-success',
            'rejected' => 'badge-danger',
            'cancelled' => 'badge-default',
        ];
        $labels = [
            'pending' => $this->module->l('Pending', 'AdminRetractationDashboardController'),
            'accepted' => $this->module->l('Accepted', 'AdminRetractationDashboardController'),
            'rejected' => $this->module->l('Rejected', 'AdminRetractationDashboardController'),
            'cancelled' => $this->module->l('Cancelled', 'AdminRetractationDashboardController'),
        ];
        $class = isset($badges[$value]) ? $badges[$value] : 'badge-info';
        $label = isset($labels[$value]) ? $labels[$value] : ucfirst($value);

        return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
