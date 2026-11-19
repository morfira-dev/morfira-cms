<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Console;

use Cms\Content\Application\Import\ImportWordPressDrafts;
use Cms\Content\Application\Import\WordPressImportClaimConflict;
use Cms\Content\Infrastructure\Import\WordPressExportReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cms:import:wordpress', description: 'Plan or import drafts from a local WordPress WXR export.')]
final class ImportWordPressCommand extends Command
{
    public function __construct(
        private readonly WordPressExportReader $reader,
        private readonly ImportWordPressDrafts $import,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Path to the local WXR export.')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Stable key for the WordPress installation.')
            ->addOption('media-dir', null, InputOption::VALUE_REQUIRED, 'Local directory containing the WordPress uploads tree.')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Create drafts. Omit to inspect the plan.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('file');
        $source = $input->getOption('source');
        $mediaDirectory = $input->getOption('media-dir');
        $apply = $input->getOption('apply') === true;
        if (!is_string($path) || !is_string($source) || ($mediaDirectory !== null && !is_string($mediaDirectory))) {
            return $this->fail($output, 'A local export path and --source are required.', Command::INVALID);
        }

        try {
            $export = $this->reader->read($path);
        } catch (\InvalidArgumentException|\RuntimeException $error) {
            return $this->fail($output, $error->getMessage(), Command::INVALID);
        }

        try {
            $report = ($this->import)($source, $export['items'], $apply, $export['terms'], $export['attachments'], $mediaDirectory, $export['authors'], $export['sourceUrl']);
        } catch (WordPressImportClaimConflict) {
            return $this->fail($output, 'Another import claimed an item. Apply was rolled back. Run the plan again.', Command::FAILURE);
        } catch (\InvalidArgumentException) {
            return $this->fail($output, 'Invalid source key or draft inputs. Check the options and run the plan again.', Command::INVALID);
        } catch (\Throwable) {
            return $this->fail($output, 'Import failed. Check migrations and database connectivity, then run the plan again.', Command::FAILURE);
        }

        $counts = array_count_values(array_column($report['items'], 'state'));
        $output->writeln(json_encode([
            'mode' => $apply ? 'apply' : 'dry-run',
            'source' => $source,
            'counts' => (object) $counts,
            'skippedTypes' => (object) $export['skippedTypes'],
            'items' => $report['items'],
            'media' => $report['media'],
            'taxonomy' => $report['taxonomy'],
            'authors' => $report['authors'],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    private function fail(OutputInterface $output, string $message, int $code): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $errorOutput->writeln($message, OutputInterface::OUTPUT_RAW);

        return $code;
    }
}
