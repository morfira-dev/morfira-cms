<?php

declare(strict_types=1);

use Cms\Content\Application\Import\WordPressRestCredentials;
use Cms\Content\Infrastructure\Import\WordPressRestJsonTransport;
use Cms\Content\Infrastructure\Import\WordPressRestTransport;
use Cms\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;

require '/app/back/vendor/autoload.php';

$capture = getenv('MORFIRA_REHEARSAL_CAPTURE');
if ($capture === false || !str_starts_with($capture, '/')) {
    throw new RuntimeException('Set MORFIRA_REHEARSAL_CAPTURE to the local fixture output directory.');
}
$inventory = json_decode((string) file_get_contents($capture.'/inventory.json'), true, flags: JSON_THROW_ON_ERROR);
$origin = $inventory['site'] ?? '';
if (!is_string($origin) || preg_match('~\Ahttp://localhost:[0-9]+\z~', $origin) !== 1) {
    throw new RuntimeException('The fixture inventory must identify its loopback WordPress origin.');
}

$kernel = new Kernel('test', false);
$kernel->boot();
$kernel->getContainer()->get('test.service_container')->set(WordPressRestTransport::class, new class($capture, $origin) implements WordPressRestJsonTransport {
    public function __construct(private readonly string $capture, private readonly string $origin)
    {
    }

    public function get(string $baseUrl, string $resource, array $query, ?WordPressRestCredentials $credentials = null, ?int $deadlineNs = null, ?int $maxBodyBytes = null): array
    {
        if ($baseUrl !== 'https://wordpress.example/wp-json/wp/v2' || $credentials !== null || ($query['page'] ?? null) !== 1
            || !in_array($resource, ['posts', 'pages', 'media', 'categories', 'tags', 'users'], true)) {
            throw new RuntimeException('Unsupported captured fixture request.');
        }

        $json = file_get_contents($this->capture.'/rest/'.$resource.'.json');
        if ($json === false) {
            throw new RuntimeException('Missing captured WordPress collection.');
        }
        $rows = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 50) {
            throw new RuntimeException('The capture must contain one page of WordPress rows.');
        }
        array_walk_recursive($rows, function (mixed &$value): void {
            if (is_string($value)) {
                $value = str_replace($this->origin, 'https://wordpress.example', $value);
            }
        });

        return ['body' => $rows, 'totalPages' => $rows === [] ? 0 : 1, 'bodyBytes' => strlen($json)];
    }
});

$application = new Application($kernel);
$application->setDefaultCommand('cms:import:wordpress', true);
exit($application->run(new ArgvInput()));
