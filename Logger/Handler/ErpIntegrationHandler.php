<?php
namespace Custom\ErpIntegration\Logger\Handler;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class ErpIntegrationHandler extends StreamHandler
{
    public function __construct($level = Logger::INFO, $bubble = true)
    {
        $logFile = BP . '/var/log/magento.erp_integration.log';
        parent::__construct($logFile, $level, $bubble);
    }
}
