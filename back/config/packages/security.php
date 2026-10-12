<?php

declare(strict_types=1);

use Cms\Identity\Infrastructure\Security\JsonAuthenticationEntryPoint;
use Cms\Identity\Infrastructure\Security\JsonLoginFailureHandler;
use Cms\Identity\Infrastructure\Security\JsonLoginSuccessHandler;
use Cms\Identity\Infrastructure\Security\SecurityUser;
use Cms\Identity\Infrastructure\Security\SecurityUserProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('security', [
        'password_hashers' => [
            SecurityUser::class => ['algorithm' => 'auto'],
        ],
        'providers' => [
            'cms_users' => ['id' => SecurityUserProvider::class],
        ],
        'firewalls' => [
            'main' => [
                'lazy' => true,
                'provider' => 'cms_users',
                'entry_point' => JsonAuthenticationEntryPoint::class,
                'json_login' => [
                    'check_path' => '/api/v1/auth/login',
                    'username_path' => 'email',
                    'password_path' => 'password',
                    'success_handler' => JsonLoginSuccessHandler::class,
                    'failure_handler' => JsonLoginFailureHandler::class,
                ],
                'login_throttling' => [
                    'max_attempts' => 5,
                    'interval' => '1 minute',
                ],
                'logout' => [
                    'path' => '/api/v1/auth/logout',
                    'invalidate_session' => true,
                ],
            ],
        ],
        'access_control' => [
            ['path' => '^/api/v1/admin', 'roles' => 'ROLE_EDITOR'],
            ['path' => '^/_preview', 'roles' => 'ROLE_EDITOR'],
        ],
    ]);
};
