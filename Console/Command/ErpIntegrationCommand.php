<?php

declare(strict_types=1);

namespace Custom\ErpIntegration\Console\Command;

use Custom\ErpIntegration\Model\ProductImporter;
use Custom\ErpIntegration\Service\ProductActionService;
use Custom\ErpIntegration\Logger\ErpIntegrationLogger;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ErpIntegrationCommand extends Command
{
    public const FILE_ARGUMENT = 'file';
    private const DEFAULT_FILE = 'var/import/erp_products.json';
    private ProductImporter $productImporter;
    private State $appState;
    private ScopeConfigInterface $scopeConfig;
    private ProductActionService $productActionService;
    private ErpIntegrationLogger $erpLogger;

    public function __construct(
        ProductImporter $productImporter,
        State $appState,
        ScopeConfigInterface $scopeConfig,
        ProductActionService $productActionService,
        ErpIntegrationLogger $erpLogger,
    ) {
        $this->productImporter = $productImporter;
        $this->appState = $appState;
        $this->scopeConfig = $scopeConfig;
        $this->productActionService = $productActionService;
        $this->erpLogger = $erpLogger;
        parent::__construct();
    }


    /**
     * Configures the settings or parameters for the current context.
     *
     * This function is typically used to set up or customize options before execution.
     * It allows for flexible adjustment of behavior based on provided configuration values.
     *
     * @param array $options Optional. An associative array of configuration options.
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('erp:integration:run')
            ->setDescription('Processes ERP product integration actions (update, new, enable, disable) from JSON file')
            ->addArgument(
                self::FILE_ARGUMENT,
                InputArgument::OPTIONAL,
                'Path to the JSON file (default: var/import/erp_products.json)'
            );
        parent::configure();
    }

    /**
     * Command class for ERP Integration.
     *
     * This class defines the console command for ERP integration tasks.
     * 
     * The `execute` function is responsible for handling the main logic
     * when the command is run from the Magento CLI. It should contain
     * the integration process or trigger the required ERP actions.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException $e) {
            // Ignore if already set
        }

        $filePath = $input->getArgument(self::FILE_ARGUMENT);
        if (!$filePath) {
            $filePath = $this->scopeConfig->getValue('erp_integration/general/products_json_path') ?: self::DEFAULT_FILE;
        }

        try {
            $products = $this->productImporter->readErpProducts($filePath);
        } catch (\Throwable $t) {
            $msg = 'Failed to read ERP file: ' . $t->getMessage();
            $output->writeln('<error>' . $msg . '</error>');
            $this->erpLogger->error($msg);
            return Command::FAILURE;
        }

        if (empty($products)) {
            $msg = 'No products found in file.';
            $output->writeln('<error>' . $msg . '</error>');
            $this->erpLogger->error($msg);
            return Command::FAILURE;
        }

        $updated = 0;
        $created = 0;
        $disabled = 0;
        $enabled = 0;
        $failReasons = [];

        foreach ($products as $idx => $productData) {
            $action = $productData['action'] ?? 'update';
            $sku   = $productData['sku']   ?? null;
            if (!$sku) {
                $failMsg = sprintf('Record #%d: Product data missing SKU, cannot process.', $idx + 1);
                $failReasons[] = $failMsg;
                continue;
            }
            $failMsg = null;
            try {
                if ($action === 'enable') {
                    $enabledResult = $this->productActionService->enableProduct($sku, $failMsg);
                    if ($enabledResult) {
                        $msg = "Enabled: $sku";
                        $output->writeln('<info>' . $msg . '</info>');
                        $this->erpLogger->info($msg);
                        $enabled++;
                    } else {
                        $failMsgStr = $failMsg ?? 'Unknown error';
                        $failReasons[] = $failMsgStr;
                    }
                } elseif ($action === 'disable') {
                    $disabledResult = $this->productActionService->disableProduct($sku, $failMsg);
                    if ($disabledResult) {
                        $msg = "Disabled: $sku";
                        $output->writeln('<info>' . $msg . '</info>');
                        $this->erpLogger->info($msg);
                        $disabled++;
                    } else {
                        $failMsgStr = $failMsg ?? 'Unknown error';
                        $failReasons[] = $failMsgStr;
                    }
                } elseif ($action === 'new') {
                    $createdResult = $this->productActionService->createProduct($productData, $failMsg);
                    if ($createdResult) {
                        $msg = "Created: $sku";
                        $output->writeln('<info>' . $msg . '</info>');
                        $this->erpLogger->info($msg);
                        $created++;
                    } else {
                        $failMsgStr = $failMsg ?? 'Unknown error';
                        $failReasons[] = $failMsgStr;
                    }
                } else {
                    $updatedResult = $this->productActionService->updateProduct($productData, $failMsg);
                    if ($updatedResult) {
                        $msg = "Updated: $sku";
                        $output->writeln('<info>' . $msg . '</info>');
                        $this->erpLogger->info($msg);
                        $updated++;
                    } else {
                        $failMsgStr = $failMsg ?? 'Unknown error';
                        $failReasons[] = $failMsgStr;
                    }
                }
            } catch (\Throwable $e) {
                $msg = "Failed to process SKU: $sku - " . $e->getMessage();
                $failReasons[] = $msg;
                $this->erpLogger->error($msg);
            }
        }

        if ($updated === 0 && $created === 0 && $disabled === 0 && $enabled === 0) {
            $msg = 'No records were processed.';
            $output->writeln('<comment>' . $msg . '</comment>');
            $this->erpLogger->info($msg);
        }
        foreach ($failReasons as $failMsg) {
            $output->writeln('<error>' . $failMsg . '</error>');
            $this->erpLogger->error($failMsg);
        }
        $msg = sprintf('Total updated: %d | created: %d | disabled: %d | enabled: %d', $updated, $created, $disabled, $enabled);
        $output->writeln('<comment>' . $msg . '</comment>');
        $this->erpLogger->info($msg);
            return Command::SUCCESS;
        }
    }
    