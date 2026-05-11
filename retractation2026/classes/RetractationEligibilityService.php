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
            'SELECT MIN(od.is_virtual) FROM `' . _DB_PREFIX_ . 'order_detail` od
             WHERE od.id_order = ' . $idOrder
        );
        if ($hasOnlyVirtual) {
            return $this->ineligible('Order contains only virtual/downloadable products (L221-28)');
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

    private function getStateDate(int $idOrder, string $stateColumn): ?string
    {
        $sql = 'SELECT oh.date_add
                FROM `' . _DB_PREFIX_ . 'order_history` oh
                INNER JOIN `' . _DB_PREFIX_ . 'order_state` os
                    ON oh.id_order_state = os.id_order_state
                WHERE oh.id_order = ' . (int) $idOrder . '
                    AND os.`' . bqSQL($stateColumn) . '` = 1
                ORDER BY oh.date_add ASC';

        $result = Db::getInstance()->getValue($sql);

        return $result ?: null;
    }

    private function computeResult(string $referenceDate, int $delayDays, int $bufferDays, string $source): array
    {
        $reference = new DateTimeImmutable($referenceDate);
        $totalDays = $delayDays + $bufferDays;
        $deadline = $reference->modify('+' . $totalDays . ' days');
        $now = new DateTimeImmutable();
        $eligible = $deadline > $now;

        return [
            'eligible' => $eligible,
            'deadline' => $deadline->format('Y-m-d H:i:s'),
            'source' => $source,
            'reference_date' => $reference->format('Y-m-d H:i:s'),
            'reason' => $eligible ? null : 'Retractation period has expired',
        ];
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
