<?php
/**
 * @author    GSD
 * @copyright GSD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Retractation extends ObjectModel
{
    public $id_retractation;
    public $id_order;
    public $id_customer;
    public $id_shop;
    public $reason;
    public $status;
    public $retractation_date;
    public $deadline_date;
    public $deadline_source;
    public $ip_address;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'retractation',
        'primary' => 'id_retractation',
        'fields' => [
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'reason' => ['type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'retractation_date' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'deadline_date' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'deadline_source' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName'],
            'ip_address' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];
}
