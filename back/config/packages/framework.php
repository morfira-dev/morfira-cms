<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('framework', [
        'secret' => '%env(APP_SECRET)%',
        'http_method_override' => false,
        'handle_all_throwables' => true,
        'router' => ['utf8' => true],
        'session' => [
            'name' => 'CMSSESSID',
            'cookie_httponly' => true,
            'cookie_samesite' => 'lax',
            'cookie_secure' => 'auto',
        ],
        'csrf_protection' => true,
    ]);
};
