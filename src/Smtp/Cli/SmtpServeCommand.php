<?php

namespace App\Smtp\Cli;

use App\Smtp\Protocol\Server\SmtpServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'smtp:serve')]
class SmtpServeCommand extends Command
{
    public function __construct(
        private SmtpServer $server,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->server->start('0.0.0.0', 25);
        return Command::SUCCESS;
    }
}
