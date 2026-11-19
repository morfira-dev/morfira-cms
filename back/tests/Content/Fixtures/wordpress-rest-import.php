<?php

declare(strict_types=1);

use Cms\Content\Infrastructure\Import\WordPressRestTransport;
use Cms\Content\Infrastructure\Import\WordPressRestJsonTransport;
use Cms\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\DependencyInjection\ContainerInterface;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
if (!$container instanceof ContainerInterface) {
    throw new RuntimeException('The fixture requires the test service container.');
}
$container->set(WordPressRestTransport::class, new class implements WordPressRestJsonTransport {
    /** @param array<string,int|string> $query */
    public function get(string $baseUrl, string $resource, array $query): array
    {
        if ($baseUrl !== 'https://wordpress.example/wp-json/wp/v2' || $query['context'] !== 'view' || $query['page'] !== 1) {
            throw new RuntimeException('Unexpected fixture request.');
        }
        $records = match ($resource) {
            'posts' => [[
                'id' => 92001,
                'status' => 'publish',
                'slug' => 'imported-rest-post',
                'title' => ['rendered' => getenv('CMS_REST_FIXTURE_EDITED') === '1' ? 'Edited REST post' : 'Imported REST post'],
                'content' => ['rendered' => '<p>REST body</p>', 'protected' => false],
                'link' => 'https://wordpress.example/blog/imported-rest-post/',
                'author' => 92002,
                'categories' => [92003],
            ]],
            'users' => [['id' => 92002, 'slug' => 'fixture-author', 'name' => 'REST Fixture Author']],
            'categories' => [['id' => 92003, 'slug' => 'rest-fixture-category', 'name' => 'REST Fixture Category', 'parent' => 0]],
            'pages', 'media', 'tags' => [],
            default => throw new RuntimeException('Unknown fixture collection.'),
        };

        return ['body' => $records, 'totalPages' => $records === [] ? 0 : 1];
    }
});

$application = new Application($kernel);
$application->setDefaultCommand('cms:import:wordpress', true);
exit($application->run(new ArgvInput()));
