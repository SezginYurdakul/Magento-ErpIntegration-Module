<?php

declare(strict_types=1);

namespace Custom\ErpIntegration\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

use Custom\ErpIntegration\Logger\ErpIntegrationLogger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class OrderCancelAfterObserver implements ObserverInterface
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
            $this->erpLogger->error("Order object is missing, could not update ERP JSON on cancel.");
            return;
        }
        $incrementId = method_exists($order, 'getIncrementId') ? $order->getIncrementId() : null;
        if (!$incrementId) {
            $this->erpLogger->error("Order increment_id is missing, cannot update ERP JSON on cancel.");
            return;
        }
        $configPath = 'erp_integration/general/orders_json_path';
        $relativePath = $this->scopeConfig->getValue($configPath, ScopeInterface::SCOPE_STORE);
        $filePath = $relativePath ? BP . '/' . ltrim($relativePath, '/') : BP . '/var/export/erp_orders.json';
        if (!file_exists($filePath)) {
            $this->erpLogger->comment("ERP JSON file does not exist, nothing to update for order #$incrementId.");
            return;
        }
        $json = file_get_contents($filePath);
        $ordersData = json_decode($json, true) ?: [];
        $found = false;
        foreach ($ordersData as &$orderData) {
            if (($orderData['increment_id'] ?? null) == $incrementId) {
                $orderData['status'] = 'canceled';
                $found = true;
                break;
            }
        }
        unset($orderData);
        if ($found) {
            try {
                file_put_contents($filePath, json_encode($ordersData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $this->erpLogger->info("Order #$incrementId marked as canceled in ERP JSON file.");
            } catch (\Throwable $e) {
                $this->erpLogger->error("Failed to update ERP JSON for canceled order #$incrementId: " . $e->getMessage());
            }
        } else {
            $this->erpLogger->comment("Order #$incrementId not found in ERP JSON file, cannot mark as canceled.");
        }
    }
}
