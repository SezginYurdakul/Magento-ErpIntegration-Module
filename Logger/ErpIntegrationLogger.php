<?php


namespace Custom\ErpIntegration\Logger;

use Magento\Framework\Logger\Monolog;
use Symfony\Component\Console\Output\OutputInterface;

class ErpIntegrationLogger
{
    /**
     * @var Monolog
     */
    private $logger;

    public function __construct(Monolog $logger)
    {
        $this->logger = $logger;
    }

    public function info(string $msg, ?OutputInterface $output = null): void
    {
        if ($output) {
            $output->writeln('<info>' . $msg . '</info>');
        }
        $this->logger->info($msg);
    }

    public function error(string $msg, ?OutputInterface $output = null): void
    {
        if ($output) {
            $output->writeln('<error>' . $msg . '</error>');
        }
        $this->logger->error($msg);
    }

    public function comment(string $msg, ?OutputInterface $output = null): void
    {
        if ($output) {
            $output->writeln('<comment>' . $msg . '</comment>');
        }
        $this->logger->info($msg);
    }
}
