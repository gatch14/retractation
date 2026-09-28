<?php
/**
 * @author    GSD
 * @copyright GSD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class RetractationEligibilityService
{
    public function getEligibility(int $idOrder): array
    {
        $idOrder = (int) $idOrder;

        if ($idOrder <= 0) {
            return $this->ineligible('Invalid order ID');
        }

        if (!Configuration::get('RETRACTATION_ENABLED')) {
            return $this->ineligible('Module disabled');
        }

        $order = new Order($idOrder);

        if (!Validate::isLoadedObject($order)) {
            return $this->ineligible('Order not found');
        }

        $cancelledState = (int) Configuration::get('PS_OS_CANCELED');
        $refundedState = (int) Configuration::get('PS_OS_REFUND');

        if ((int) $order->current_state === $cancelledState || (int) $order->current_state === $refundedState) {
            return $this->ineligible('Order is cancelled or refunded');
        }

        // L221-28: virtual/downloadable products are exempt from the right of withdrawal
        $hasOnlyVirtual = (bool) Db::getInstance()->getValue(
            'SELECT MIN(p.is_virtual) FROM `' . _DB_PREFIX_ . 'order_detail` od
             JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = od.product_id
             WHERE od.id_order = ' . $idOrder
        );
        if ($hasOnlyVirtual) {
            return $this->ineligible('Order contains only virtual/downloadable products (L221-28)');
        }

        if ($this->hasExcludedProduct($idOrder)) {
            return $this->ineligible('Order contains products excluded from the right of withdrawal (L221-28)');
        }

        $delayDays = (int) Configuration::get('RETRACTATION_DELAY_DAYS');
        $bufferShipped = (int) Configuration::get('RETRACTATION_BUFFER_SHIPPED');
        $bufferOrder = (int) Configuration::get('RETRACTATION_BUFFER_ORDER');

        $deliveredDate = $this->getStateDate($idOrder, 'delivery');

        if ($deliveredDate) {
            return $this->computeResult($deliveredDate, $delayDays, 0, 'delivered');
        }

        $shippedDate = $this->getStateDate($idOrder, 'shipped');

        if ($shippedDate) {
            return $this->computeResult($shippedDate, $delayDays, $bufferShipped, 'shipped');
        }

        return $this->computeResult($order->date_add, $delayDays, $bufferOrder, 'order');
    }

    /**
     * Check whether the order contains a product (or a product in a category)
     * that the merchant configured as excluded from the right of withdrawal.
     */
    private function hasExcludedProduct(int $idOrder): bool
    {
        $excludedProducts = $this->parseIdList(Configuration::get('RETRACTATION_EXCLUDED_PRODUCTS'));
        $excludedCategories = $this->parseIdList(Configuration::get('RETRACTATION_EXCLUDED_CATEGORIES'));

        if (empty($excludedProducts) && empty($excludedCategories)) {
            return false;
        }

        $db = Db::getInstance();

        if (!empty($excludedProducts)) {
            $inProducts = implode(',', array_map('intval', $excludedProducts));
            $found = (bool) $db->getValue(
                'SELECT 1 FROM `' . _DB_PREFIX_ . 'order_detail`
                 WHERE `id_order` = ' . $idOrder . '
                   AND `product_id` IN (' . $inProducts . ')
                 LIMIT 1'
            );
            if ($found) {
                return true;
            }
        }

        if (!empty($excludedCategories)) {
            $inCategories = implode(',', array_map('intval', $excludedCategories));
            $found = (bool) $db->getValue(
                'SELECT 1 FROM `' . _DB_PREFIX_ . 'order_detail` od
                 JOIN `' . _DB_PREFIX_ . 'category_product` cp
                   ON cp.`id_product` = od.`product_id`
                 WHERE od.`id_order` = ' . $idOrder . '
                   AND cp.`id_category` IN (' . $inCategories . ')
                 LIMIT 1'
            );
            if ($found) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return int[]
     */
    private function parseIdList($value): array
    {
        if (empty($value)) {
            return [];
        }

        return array_filter(array_map('intval', explode(',', $value)));
    }

    private function getStateDate(int $idOrder, string $stateColumn): ?string
    {
        $sql = 'SELECT oh.date_add
                FROM `' . _DB_PREFIX_ . 'order_history` oh
                INNER JOIN `' . _DB_PREFIX_ . 'order_state` os
                    ON oh.id_order_state = os.id_order_state
                WHERE oh.id_order = ' . (int) $idOrder . '
                    AND os.`' . bqSQL($stateColumn) . '` = 1
                ORDER BY oh.date_add DESC';

        $result = Db::getInstance()->getValue($sql);

        return $result ?: null;
    }

    private function computeResult(string $referenceDate, int $delayDays, int $bufferDays, string $source): array
    {
        $reference = new DateTimeImmutable($referenceDate);
        $totalDays = $delayDays + $bufferDays;
        $deadline = $this->adjustDeadlineForHolidays($reference->modify('+' . $totalDays . ' days'));
        $now = new DateTimeImmutable();
        $eligible = $deadline >= $now;

        return [
            'eligible' => $eligible,
            'deadline' => $deadline->format('Y-m-d H:i:s'),
            'source' => $source,
            'reference_date' => $reference->format('Y-m-d H:i:s'),
            'reason' => $eligible ? null : 'Retractation period has expired',
        ];
    }

    /**
     * Extend the deadline to the next business day if it falls on a Saturday,
     * Sunday or French public holiday (article L.221-18 al. 2).
     */
    private function adjustDeadlineForHolidays(DateTimeImmutable $deadline): DateTimeImmutable
    {
        $holidays = $this->getFrenchHolidays((int) $deadline->format('Y'));

        while (in_array($deadline->format('Y-m-d'), $holidays, true)
            || (int) $deadline->format('N') >= 6
        ) {
            $deadline = $deadline->modify('+1 day');
            $holidays = $this->getFrenchHolidays((int) $deadline->format('Y'));
        }

        return $deadline;
    }

    /**
     * @return string[]
     */
    private function getFrenchHolidays(int $year): array
    {
        $easter = new DateTimeImmutable('@' . easter_date($year));

        $dates = [
            $easter->modify('+1 day')->format('Y-m-d'),      // Lundi de Pâques
            $easter->modify('+39 days')->format('Y-m-d'),    // Ascension
            $easter->modify('+50 days')->format('Y-m-d'),    // Lundi de Pentecôte
            "$year-01-01", // Jour de l’an
            "$year-05-01", // Fête du travail
            "$year-05-08", // Victoire 1945
            "$year-07-14", // Fête nationale
            "$year-08-15", // Assomption
            "$year-11-01", // Toussaint
            "$year-11-11", // Armistice
            "$year-12-25", // Noël
        ];

        return $dates;
    }

    private function ineligible(string $reason): array
    {
        return [
            'eligible' => false,
            'deadline' => null,
            'source' => null,
            'reference_date' => null,
            'reason' => $reason,
        ];
    }
}
