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
            'pending' => $this->trans('Pending', [], 'Modules.Retractation2026.Admin'),
            'accepted' => $this->trans('Accepted', [], 'Modules.Retractation2026.Admin'),
            'rejected' => $this->trans('Rejected', [], 'Modules.Retractation2026.Admin'),
            'cancelled' => $this->trans('Cancelled', [], 'Modules.Retractation2026.Admin'),
        ];

        $this->fields_list = [
            'id_retractation' => [
                'title' => $this->trans('ID', [], 'Modules.Retractation2026.Admin'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'order_reference' => [
                'title' => $this->trans('Order', [], 'Modules.Retractation2026.Admin'),
                'filter_key' => 'o!reference',
                'havingFilter' => false,
            ],
            'customer_name' => [
                'title' => $this->trans('Customer', [], 'Modules.Retractation2026.Admin'),
                'filter_key' => 'c!lastname',
                'havingFilter' => true,
            ],
            'status' => [
                'title' => $this->trans('Status', [], 'Modules.Retractation2026.Admin'),
                'type' => 'select',
                'list' => $statusList,
                'filter_key' => 'a!status',
                'callback' => 'getStatusBadge',
            ],
            'retractation_date' => [
                'title' => $this->trans('Retractation date', [], 'Modules.Retractation2026.Admin'),
                'type' => 'datetime',
                'filter_key' => 'a!retractation_date',
            ],
            'deadline_date' => [
                'title' => $this->trans('Deadline', [], 'Modules.Retractation2026.Admin'),
                'type' => 'datetime',
                'filter_key' => 'a!deadline_date',
            ],
            'deadline_source' => [
                'title' => $this->trans('Source', [], 'Modules.Retractation2026.Admin'),
                'filter_key' => 'a!deadline_source',
            ],
            'date_add' => [
                'title' => $this->trans('Created', [], 'Modules.Retractation2026.Admin'),
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
            $this->errors[] = $this->trans('Retractation not found.', [], 'Modules.Retractation2026.Admin');
            return parent::renderList();
        }

        // CR-03: POST forms instead of GET links to prevent CSRF via XSS
        $statusActions = '';
        if ($row['status'] === 'pending') {
            $baseUrl = $this->context->link->getAdminLink('AdminRetractationDashboard');
            $statusActions = '<form method="post" action="' . $baseUrl . '" style="display:inline-block;margin-right:4px">'
                . '<input type="hidden" name="id_retractation" value="' . $id . '" />'
                . '<input type="hidden" name="statusretractation" value="1" />'
                . '<input type="hidden" name="new_status" value="accepted" />'
                . '<button type="submit" class="btn btn-success"><i class="icon-check"></i> ' . $this->trans('Accept', [], 'Modules.Retractation2026.Admin') . '</button>'
                . '</form>';
            $statusActions .= '<form method="post" action="' . $baseUrl . '">'
                . '<input type="hidden" name="id_retractation" value="' . $id . '" />'
                . '<input type="hidden" name="statusretractation" value="1" />'
                . '<input type="hidden" name="new_status" value="rejected" />'
                . '<div class="form-group" style="margin-top:12px">'
                . '<label><strong>' . $this->trans('Rejection reason (required)', [], 'Modules.Retractation2026.Admin') . '</strong></label>'
                . '<textarea name="reject_reason" class="form-control" rows="3" maxlength="1000" required placeholder="' . $this->trans('Enter the reason for rejection...', [], 'Modules.Retractation2026.Admin') . '"></textarea>'
                . '</div>'
                . '<button type="submit" class="btn btn-danger"><i class="icon-remove"></i> ' . $this->trans('Reject', [], 'Modules.Retractation2026.Admin') . '</button>'
                . '</form>';
        }

        $html = '<div class="panel">';
        $html .= '<div class="panel-heading"><i class="icon-eye"></i> ' . $this->trans('Retractation', [], 'Modules.Retractation2026.Admin') . ' #' . $id . '</div>';
        $html .= '<div class="row">';
        $html .= '<div class="col-lg-6">';
        $html .= '<table class="table">';
        $orderLink = $this->context->link->getAdminLink('AdminOrders', true, [], ['id_order' => (int)$row['id_order'], 'vieworder' => 1]);
        $html .= '<tr><td><strong>' . $this->trans('Order', [], 'Modules.Retractation2026.Admin') . '</strong></td><td><a href="' . $orderLink . '">' . htmlspecialchars($row['order_reference'], ENT_QUOTES, 'UTF-8') . ' <i class="icon-external-link"></i></a></td></tr>';
        $html .= '<tr><td><strong>' . $this->trans('Customer', [], 'Modules.Retractation2026.Admin') . '</strong></td><td>' . htmlspecialchars($row['customer_name'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->trans('Email', [], 'Modules.Retractation2026.Admin') . '</strong></td><td>' . htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->trans('Status', [], 'Modules.Retractation2026.Admin') . '</strong></td><td>' . $this->getStatusBadge($row['status']) . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->trans('Retractation date', [], 'Modules.Retractation2026.Admin') . '</strong></td><td>' . htmlspecialchars($row['retractation_date'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->trans('Deadline', [], 'Modules.Retractation2026.Admin') . '</strong></td><td>' . htmlspecialchars($row['deadline_date'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->trans('Source', [], 'Modules.Retractation2026.Admin') . '</strong></td><td>' . htmlspecialchars($row['deadline_source'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->trans('IP', [], 'Modules.Retractation2026.Admin') . '</strong></td><td>' . htmlspecialchars($row['ip_address'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td><strong>' . $this->trans('Created', [], 'Modules.Retractation2026.Admin') . '</strong></td><td>' . htmlspecialchars($row['date_add'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        $html .= '<div class="col-lg-6">';
        $html .= '<div class="panel"><div class="panel-heading">' . $this->trans('Customer reason', [], 'Modules.Retractation2026.Admin') . '</div>';
        $html .= '<p>' . nl2br(htmlspecialchars($row['reason'] ?? '', ENT_QUOTES, 'UTF-8')) . '</p>';
        $html .= '</div>';
        if ($row['status'] === 'rejected' && !empty($row['reject_reason'])) {
            $html .= '<div class="panel panel-danger"><div class="panel-heading">' . $this->trans('Rejection reason', [], 'Modules.Retractation2026.Admin') . '</div>';
            $html .= '<p>' . nl2br(htmlspecialchars($row['reject_reason'], ENT_QUOTES, 'UTF-8')) . '</p>';
            $html .= '</div>';
        }
        if ($statusActions) {
            $html .= '<div class="panel"><div class="panel-heading">' . $this->trans('Actions', [], 'Modules.Retractation2026.Admin') . '</div>';
            $html .= $statusActions;
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        $backUrl = $this->context->link->getAdminLink('AdminRetractationDashboard');
        $html .= '<a href="' . $backUrl . '" class="btn btn-default"><i class="icon-arrow-left"></i> ' . $this->trans('Back to list', [], 'Modules.Retractation2026.Admin') . '</a>';

        return $html;
    }

    public function postProcess()
    {
        if (Tools::getIsset('statusretractation') && Tools::getValue('id_retractation') && Tools::getValue('new_status')
            && $_SERVER['REQUEST_METHOD'] === 'POST'
        ) {
            $id = (int) Tools::getValue('id_retractation');
            $newStatus = pSQL(Tools::getValue('new_status'));
            $allowed = ['accepted', 'rejected', 'cancelled'];
            if (!in_array($newStatus, $allowed)) {
                $this->errors[] = $this->trans('Invalid status.', [], 'Modules.Retractation2026.Admin');
                return;
            }

            $rejectReason = null;
            if ($newStatus === 'rejected') {
                $rejectReason = strip_tags(trim(Tools::getValue('reject_reason', '')));
                if (empty($rejectReason)) {
                    $this->errors[] = $this->trans('Please enter a rejection reason.', [], 'Modules.Retractation2026.Admin');
                    return;
                }
                if (mb_strlen($rejectReason) > 1000) {
                    $rejectReason = mb_substr($rejectReason, 0, 1000);
                }
            }

            $updateData = [
                'status' => $newStatus,
                'date_upd' => date('Y-m-d H:i:s'),
            ];
            if ($rejectReason !== null) {
                $updateData['reject_reason'] = pSQL($rejectReason);
            }

            $result = Db::getInstance()->update('retractation', $updateData, 'id_retractation = ' . $id . ' AND id_shop = ' . (int) Shop::getContextShopID());

            if ($result) {
                $this->sendStatusEmail($id, $newStatus);
                $this->confirmations[] = $this->trans('Status updated.', [], 'Modules.Retractation2026.Admin');
            } else {
                $this->errors[] = $this->trans('Could not update status.', [], 'Modules.Retractation2026.Admin');
            }
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminRetractationDashboard') . '&viewretractation&id_retractation=' . $id);
        }
        return parent::postProcess();
    }

    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
        $this->page_header_toolbar_title = $this->trans('Retractation requests', [], 'Modules.Retractation2026.Admin');
    }

    private function sendStatusEmail($idRetractation, $status)
    {
        // CR-04: cancelled is not a rejection — no email to avoid legal confusion
        if (!in_array($status, ['accepted', 'rejected'])) {
            return;
        }

        // CR-09: id_shop filter prevents cross-shop data leak in multi-shop
        $row = Db::getInstance()->getRow(
            'SELECT r.*, o.reference as order_reference, c.firstname, c.lastname, c.email as customer_email
             FROM `' . _DB_PREFIX_ . 'retractation` r
             LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order = r.id_order
             LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = r.id_customer
             WHERE r.id_retractation = ' . (int) $idRetractation . '
               AND r.id_shop = ' . (int) Shop::getContextShopID()
        );
        if (!$row) {
            return;
        }

        $templateVars = [
            '{firstname}' => $row['firstname'],
            '{lastname}' => $row['lastname'],
            '{order_reference}' => $row['order_reference'],
            '{retractation_date}' => Tools::displayDate($row['date_add'], (int) Context::getContext()->language->id, false),
            '{retractation_time}' => date('H:i', strtotime($row['date_add'])),
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
            '{shop_url}' => Context::getContext()->link->getBaseLink(),
            '{reject_reason}' => $status === 'rejected'
                ? htmlspecialchars($row['reject_reason'] ?? '', ENT_QUOTES, 'UTF-8')
                : '',
        ];

        $template = 'retractation_' . $status;
        $subject = $status === 'accepted'
            ? $this->trans('Your retractation has been accepted', [], 'Modules.Retractation2026.Admin')
            : $this->trans('Your retractation has been rejected', [], 'Modules.Retractation2026.Admin');

        // WR-01: removed @ suppression — failures now visible
        $sent = Mail::Send(
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
        if (!$sent) {
            $this->warnings[] = $this->trans('Email notification could not be sent.', [], 'Modules.Retractation2026.Admin');
        }
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
            'pending' => $this->trans('Pending', [], 'Modules.Retractation2026.Admin'),
            'accepted' => $this->trans('Accepted', [], 'Modules.Retractation2026.Admin'),
            'rejected' => $this->trans('Rejected', [], 'Modules.Retractation2026.Admin'),
            'cancelled' => $this->trans('Cancelled', [], 'Modules.Retractation2026.Admin'),
        ];
        $class = isset($badges[$value]) ? $badges[$value] : 'badge-info';
        $label = isset($labels[$value]) ? $labels[$value] : ucfirst($value);

        return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
