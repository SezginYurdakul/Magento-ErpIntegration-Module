<?php

declare(strict_types=1);

namespace Custom\ErpIntegration\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Custom\ErpIntegration\Logger\ErpIntegrationLogger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class OrderPlaceAfterObserver implements ObserverInterface
{
    private ErpIntegrationLogger $erpLogger;
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        ErpIntegrationLogger $erpLogger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->erpLogger = $erpLogger;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Summary of execute
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
public function execute(Observer $observer)
{
    $order = $observer->getEvent()->getOrder();
    if (!$order) {
        $this->erpLogger->error("Order object is missing, could not export to ERP JSON.");
        return;
    }

    $orderData = [
        'increment_id' => method_exists($order, 'getIncrementId') ? $order->getIncrementId() : null,
        'customer_id'  => method_exists($order, 'getCustomerId') ? $order->getCustomerId() : null,
        'created_at'   => method_exists($order, 'getCreatedAt') ? $order->getCreatedAt() : null,
        'grand_total'  => method_exists($order, 'getGrandTotal') ? $order->getGrandTotal() : null,
        'currency'     => method_exists($order, 'getOrderCurrencyCode') ? $order->getOrderCurrencyCode() : null,
        'status'       => method_exists($order, 'getStatus') ? $order->getStatus() : null,
        'store_id'     => method_exists($order, 'getStoreId') ? $order->getStoreId() : null,
        'coupon_code'  => method_exists($order, 'getCouponCode') ? $order->getCouponCode() : null,
        'discount_amount' => method_exists($order, 'getDiscountAmount') ? $order->getDiscountAmount() : null,
        'payment_method' => ($order->getPayment() && method_exists($order->getPayment(), 'getMethod')) ? $order->getPayment()->getMethod() : null,
        'billing_address' => null,
        'shipping_address' => null,
        'items'        => [],
    ];

    // Billing address
    $billing = method_exists($order, 'getBillingAddress') ? $order->getBillingAddress() : null;
    if ($billing) {
        $orderData['billing_address'] = [
            'firstname' => $billing->getFirstname(),
            'lastname'  => $billing->getLastname(),
            'street'    => is_array($billing->getStreet()) ? implode(' ', $billing->getStreet()) : $billing->getStreet(),
            'city'      => $billing->getCity(),
            'postcode'  => $billing->getPostcode(),
            'country'   => $billing->getCountryId(),
            'telephone' => $billing->getTelephone(),
        ];
    }

    // Shipping address
    $shipping = method_exists($order, 'getShippingAddress') ? $order->getShippingAddress() : null;
    if ($shipping) {
        $orderData['shipping_address'] = [
            'firstname' => $shipping->getFirstname(),
            'lastname'  => $shipping->getLastname(),
            'street'    => is_array($shipping->getStreet()) ? implode(' ', $shipping->getStreet()) : $shipping->getStreet(),
            'city'      => $shipping->getCity(),
            'postcode'  => $shipping->getPostcode(),
            'country'   => $shipping->getCountryId(),
            'telephone' => $shipping->getTelephone(),
        ];
    }

    if (method_exists($order, 'getAllVisibleItems')) {
        foreach ($order->getAllVisibleItems() as $item) {
            $orderData['items'][] = [
                'sku'         => $item->getSku(),
                'name'        => $item->getName(),
                'qty_ordered' => $item->getQtyOrdered(),
                'price'       => $item->getPrice(),
            ];
        }
    }

    $configPath = 'erp_integration/general/orders_json_path';
    $relativePath = $this->scopeConfig->getValue($configPath, ScopeInterface::SCOPE_STORE);
    $filePath = $relativePath ? BP . '/' . ltrim($relativePath, '/') : BP . '/var/export/erp_orders.json';
    $ordersData = [];
    if (file_exists($filePath)) {
        $json = file_get_contents($filePath);
        $ordersData = json_decode($json, true) ?: [];
    }
    $ordersData[] = $orderData;

    try {
        file_put_contents(
            $filePath,
            json_encode($ordersData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        $this->erpLogger->info("Order #" . ($orderData['increment_id'] ?? 'unknown') . " successfully exported to ERP JSON file.");
    } catch (\Throwable $e) {
        $this->erpLogger->error(
            "Failed to export order #" . ($orderData['increment_id'] ?? 'unknown') . " to ERP JSON file: " . $e->getMessage()
        );
    }
}



}
