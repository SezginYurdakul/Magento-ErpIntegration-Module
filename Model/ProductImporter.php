<?php
namespace Custom\ErpIntegration\Model;

use Symfony\Component\Console\Output\OutputInterface;
use Custom\ErpIntegration\Logger\ErpIntegrationLogger;

class ProductImporter
{
    /**
     * @var ErpIntegrationLogger
     */
    protected $erpLogger;

    /**
     * @param ErpIntegrationLogger $erpLogger
     */
    public function __construct(ErpIntegrationLogger $erpLogger)
    {
        $this->erpLogger = $erpLogger;
    }
    /**
     * Reads the ERP product JSON file and returns the data
     *
     * @param string $filePath
     * @return array
     */

    /**
     * Reads the ERP product JSON file from default location if not provided
     *
     * @param string|null $filePath
     * @return array
     */
    public function readErpProducts($filePath = null)
    {
        if ($filePath === null) {
            $filePath = BP . '/var/import/erp_products.json';
        }
        if (!file_exists($filePath)) {
            return [];
        }
        $json = file_get_contents($filePath);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Example: Logs the imported products (placeholder for Magento integration)
     *
     * @param array $products
     * @return void
     */
    /**
     * Prints product info to console
     *
     * @param array $products
     * @param OutputInterface|null $output
     * @return void
     */
    public function processProducts(array $products, $output = null)
    {
        foreach ($products as $product) {
            $msg = '[ERP Import] SKU: ' . ($product['sku'] ?? '-') . ', Price: ' . ($product['price'] ?? '-') . ', Qty: ' . ($product['qty'] ?? '-');
            $this->erpLogger->info($msg);
            if ($output) {
                $output->writeln($msg);
            }
        }
    }
}
