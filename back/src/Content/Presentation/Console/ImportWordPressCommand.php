<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Console;

use Cms\Content\Application\Import\ImportWordPressDrafts;
use Cms\Content\Application\Import\WordPressImportClaimConflict;
use Cms\Content\Application\Import\WordPressMediaHosts;
use Cms\Content\Application\Import\WordPressRemoteMediaFailure;
use Cms\Content\Infrastructure\Import\WordPressExportReader;
use Cms\Content\Infrastructure\Import\WordPressRestReader;
use Cms\Content\Application\Import\WordPressRestCredentials;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cms:import:wordpress', description: 'Plan or import WordPress drafts from WXR or the public REST API.')]
final class ImportWordPressCommand extends Command
{
    public function __construct(
        private readonly WordPressExportReader $reader,
        private readonly WordPressRestReader $restReader,
        private readonly ImportWordPressDrafts $import,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'Path to the local WXR export.')
            ->addOption('api-url', null, InputOption::VALUE_REQUIRED, 'Public HTTPS WordPress REST namespace ending in /wp-json/wp/v2.')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Stable key for the WordPress installation.')
            ->addOption('media-dir', null, InputOption::VALUE_REQUIRED, 'Local WordPress uploads tree. REST downloads eligible images when omitted.')
            ->addOption('media-host', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Additional HTTPS attachment hostname for REST downloads. Repeat for each CDN host.')
            ->addOption('include-private', null, InputOption::VALUE_NONE, 'Use CMS_WORDPRESS_USERNAME and CMS_WORDPRESS_APPLICATION_PASSWORD to import restricted REST content.')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Create drafts. Omit to inspect the plan.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('file');
        $apiUrl = $input->getOption('api-url');
        $source = $input->getOption('source');
        $mediaDirectory = $input->getOption('media-dir');
        $mediaHosts = $input->getOption('media-host');
        $includePrivate = $input->getOption('include-private') === true;
        $apply = $input->getOption('apply') === true;
        if (!is_string($source) || !ImportWordPressDrafts::validSourceKey($source) || ($mediaDirectory !== null && !is_string($mediaDirectory))) {
            return $this->fail($output, 'A stable lowercase --source key is required.', Command::INVALID);
        }
        if (($path === null) === ($apiUrl === null) || ($apiUrl !== null && !is_string($apiUrl)) || ($path !== null && !is_string($path))) {
            return $this->fail($output, 'Provide either a local export file or --api-url.', Command::INVALID);
        }
        $hostNames = is_array($mediaHosts) ? array_values(array_filter($mediaHosts, is_string(...))) : [];
        if (!is_array($mediaHosts) || count($hostNames) !== count($mediaHosts)) {
            return $this->fail($output, 'Provide a DNS hostname for each --media-host option.', Command::INVALID);
        }
        if ($mediaHosts !== [] && ($apiUrl === null || $mediaDirectory !== null)) {
            return $this->fail($output, '--media-host requires --api-url without --media-dir.', Command::INVALID);
        }
        if ($includePrivate && $apiUrl === null) {
            return $this->fail($output, '--include-private requires --api-url.', Command::INVALID);
        }

        try {
            $allowedMediaHosts = WordPressMediaHosts::normalize($hostNames);
            $credentials = $includePrivate ? $this->readRestCredentials() : null;
            $export = $apiUrl === null ? $this->reader->read((string) $path) : $this->restReader->read($apiUrl, $credentials);
        } catch (\InvalidArgumentException $error) {
            return $this->fail($output, $error->getMessage(), Command::INVALID);
        } catch (\RuntimeException|\JsonException $error) {
            return $this->fail($output, $error instanceof \JsonException ? 'Invalid WordPress response data.' : $error->getMessage(), $apiUrl === null ? Command::INVALID : Command::FAILURE);
        }

        try {
            $report = ($this->import)(
                $source, $export['items'], $apply, $export['terms'], $export['attachments'],
                $mediaDirectory, $export['authors'], $export['sourceUrl'], $apiUrl, $allowedMediaHosts, $credentials,
            );
        } catch (WordPressImportClaimConflict) {
            return $this->fail($output, 'Another import claimed an item. Apply was rolled back. Run the plan again.', Command::FAILURE);
        } catch (\InvalidArgumentException) {
            return $this->fail($output, 'Invalid source key or draft inputs. Check the options and run the plan again.', Command::INVALID);
        } catch (WordPressRemoteMediaFailure $error) {
            return $this->fail($output, $error->getMessage().' Apply was rolled back.', Command::FAILURE);
        } catch (\Throwable) {
            return $this->fail($output, 'Import failed. Check migrations and database connectivity, then run the plan again.', Command::FAILURE);
        }

        $counts = array_count_values(array_column($report['items'], 'state'));
        $output->writeln(json_encode([
            'mode' => $apply ? 'apply' : 'dry-run',
            'input' => $apiUrl === null ? 'wxr' : 'rest',
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

    private function readRestCredentials(): WordPressRestCredentials
    {
        $username = getenv('CMS_WORDPRESS_USERNAME');
        $password = getenv('CMS_WORDPRESS_APPLICATION_PASSWORD');
        if ($username === false || $username === '' || $password === false || $password === '') {
            throw new \InvalidArgumentException('Set CMS_WORDPRESS_USERNAME and CMS_WORDPRESS_APPLICATION_PASSWORD for --include-private.');
        }

        return new WordPressRestCredentials($username, $password);
    }

    private function fail(OutputInterface $output, string $message, int $code): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $errorOutput->writeln($message, OutputInterface::OUTPUT_RAW);

        return $code;
    }
}
