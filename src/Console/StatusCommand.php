<?php

declare(strict_types=1);

namespace App\Console;

use Contempt\Attribute\ConsoleCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[ConsoleCommand('app:status', 'Checks that the compiled application can boot.')]
final class StatusCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Contempt skeleton is ready.');

        return Command::SUCCESS;
    }
}
