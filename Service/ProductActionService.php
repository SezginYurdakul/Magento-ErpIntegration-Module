<?php

declare(strict_types=1);

namespace Custom\ErpIntegration\Service;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Custom\ErpIntegration\Logger\ErpIntegrationLogger;
use Custom\ErpIntegration\Service\ProductInputValidator;

class ProductActionService
{
    private ProductRepositoryInterface $productRepository;
    private SourceItemsSaveInterface $sourceItemsSave;
    private SourceItemInterfaceFactory $sourceItemFactory;
    private ProductFactory $productFactory;
    private ErpIntegrationLogger $erpLogger;
    private ProductInputValidator $inputValidator;
    private const DEFAULT_SOURCE = 'default';
    private const STATUS_ENABLED = 1;
    private const STATUS_DISABLED = 2;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        SourceItemsSaveInterface $sourceItemsSave,
        SourceItemInterfaceFactory $sourceItemFactory,
        ProductFactory $productFactory,
        ErpIntegrationLogger $erpLogger,
        ProductInputValidator $inputValidator
    ) {
        $this->productRepository = $productRepository;
        $this->sourceItemsSave = $sourceItemsSave;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->productFactory = $productFactory;
        $this->erpLogger = $erpLogger;
        $this->inputValidator = $inputValidator;
    }

    public function createProduct(array $productData, ?string &$failMsg = null): bool
    {
        $errors = [];
        if (!$this->inputValidator->validate($productData, $errors)) {
            $failMsg = implode("; ", $errors);
            return false;
        }
        $sku = $productData['sku'];
        $name = $productData['name'] ?? 'New Product';
        $price = $productData['price'] ?? 0;
        $attributeSetId = $productData['attribute_set_id'] ?? 4;
        $status = $productData['status'] ?? 1;
        $visibility = $productData['visibility'] ?? 4;
        $typeId = $productData['type_id'] ?? 'simple';

        // Check if product already exists
        try {
            $this->productRepository->get($sku);
            $failMsg = sprintf('Product with SKU "%s" could not be created: already exists.', $sku);
            return false;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // Product does not exist, continue to create
        }

        $sources = $productData['sources'] ?? [];
        $product = $this->productFactory->create();
        $product->setSku($sku);
        $product->setName($name);
        $product->setPrice((float)$price);
        $product->setTypeId($typeId);
        $product->setAttributeSetId((int)$attributeSetId);
        $product->setStatus((int)$status);
        $product->setVisibility((int)$visibility);
        $this->productRepository->save($product);

        $sourceItems = [];
        foreach ($sources as $sourceData) {
            $sourceCode = $sourceData['source_code'] ?? self::DEFAULT_SOURCE;
            $qty = isset($sourceData['quantity']) ? (float)$sourceData['quantity'] : null;
            if ($qty === null) continue;
            /** @var SourceItemInterface $sourceItem */
            $sourceItem = $this->sourceItemFactory->create();
            $sourceItem->setSku($sku);
            $sourceItem->setSourceCode($sourceCode);
            $sourceItem->setQuantity($qty);
            $sourceItem->setStatus($qty > 0
                ? SourceItemInterface::STATUS_IN_STOCK
                : SourceItemInterface::STATUS_OUT_OF_STOCK
            );
            $sourceItems[] = $sourceItem;
        }
        if ($sourceItems) {
            $this->sourceItemsSave->execute($sourceItems);
        }
        return true;
    }

    public function updateProduct(array $productData, ?string &$failMsg = null): bool
    {
        $errors = [];
        if (!$this->inputValidator->validate($productData, $errors)) {
            $failMsg = implode("; ", $errors);
            return false;
        }
        $sku   = $productData['sku'];
        $price = $productData['price'] ?? null;
        $product = $this->productRepository->get($sku);
        $changed = false;
        if ($price !== null) {
            $price = (float)$price;
            if ((float)$product->getPrice() !== $price) {
                $product->setPrice($price);
                $changed = true;
            }
        }
        // Multi-source support
        $sources = $productData['sources'] ?? [];
        $sourceItems = [];
        foreach ($sources as $sourceData) {
            $sourceCode = $sourceData['source_code'] ?? self::DEFAULT_SOURCE;
            $qty = isset($sourceData['quantity']) ? (float)$sourceData['quantity'] : null;
            if ($qty === null) continue;
            $newStatus = $qty > 0 ? SourceItemInterface::STATUS_IN_STOCK : SourceItemInterface::STATUS_OUT_OF_STOCK;
            $sourceItem = $this->sourceItemFactory->create();
            $sourceItem->setSku($sku);
            $sourceItem->setSourceCode($sourceCode);
            $sourceItem->setQuantity($qty);
            $sourceItem->setStatus($newStatus);
            $sourceItems[] = $sourceItem;
        }
        if ($sourceItems) {
            $this->sourceItemsSave->execute($sourceItems);
            $changed = true;
        }
        if ($changed) {
            $this->productRepository->save($product);
            return true;
        }
        $failMsg = sprintf('Product with SKU "%s" could not be updated: no changes detected.', $sku);
        return false;
    }

    public function disableProduct(string $sku, ?string &$failMsg = null): bool
    {
        try {
            $product = $this->productRepository->get($sku, false, 0);
            if ($product->getStatus() == self::STATUS_DISABLED) {
                $failMsg = sprintf('Product with SKU "%s" could not be disabled: already disabled.', $sku);
                return false;
            }
            $product->setStatus(self::STATUS_DISABLED);
            $this->productRepository->save($product);
            $msg = sprintf('Product with SKU "%s" has been disabled.', $sku);
            return true;
        } catch (\Throwable $e) {
            $failMsg = sprintf('Product with SKU "%s" could not be disabled: %s', $sku, $e->getMessage());
            return false;
        }
    }

    public function enableProduct(string $sku, ?string &$failMsg = null): bool
    {
        try {
            $product = $this->productRepository->get($sku, false, 0);
            if ($product->getStatus() == self::STATUS_ENABLED) {
                $failMsg = sprintf('Product with SKU "%s" could not be enabled: already enabled.', $sku);
                return false;
            }
            $product->setStatus(self::STATUS_ENABLED);
            $this->productRepository->save($product);
            $msg = sprintf('Product with SKU "%s" has been enabled.', $sku);
            return true;
        } catch (\Throwable $e) {
            $failMsg = sprintf('Product with SKU "%s" could not be enabled: %s', $sku, $e->getMessage());
            return false;
        }
    }
}
