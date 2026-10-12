<?php

declare(strict_types=1);

namespace Cms\Publishing\Presentation\Console;

use Cms\Publishing\Application\RunScheduledPublications;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cms:publishing:run-scheduled', description: 'Publish due page and entry schedules.')]
final class RunScheduledPublicationsCommand extends Command
{
    public function __construct(private readonly RunScheduledPublications $run)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum schedules to process.', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT);
        if (!is_int($limit) || $limit < 1 || $limit > 1000) {
            $output->writeln('<error>--limit must be an integer between 1 and 1000.</error>');
            return Command::INVALID;
        }

        $result = ($this->run)($limit);
        $output->writeln(sprintf('Processed %d schedule(s): %d completed, %d failed.', $result['processed'], $result['completed'], $result['failed']));

        return $result['failed'] === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
