<?php
namespace Custom\ErpIntegration\Cron;

use Custom\ErpIntegration\Console\Command\ErpIntegrationCommand ;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\ArrayInput;

class UpdateErpProducts
{
    /**
     * @var ErpIntegrationCommand
     */
    private $syncCommand;

    public function __construct(ErpIntegrationCommand $syncCommand)
    {
        $this->syncCommand = $syncCommand;
    }

    /**
     * Execute the cron job
     */
    public function execute()
    {
        $input = new ArrayInput([]);
        $output = new NullOutput();
        $this->syncCommand->run($input, $output);
        return $this;
    }
}
