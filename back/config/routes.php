<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('../src/System/Presentation/Http/', 'attribute');
    $routes->import('../src/Content/Presentation/Http/', 'attribute');
    $routes->import('../src/Publishing/Presentation/Http/', 'attribute');
    $routes->import('../src/Identity/Presentation/Http/', 'attribute');
    $routes->import('../src/Media/Presentation/Http/', 'attribute');
    $routes->import('../src/Navigation/Presentation/Http/', 'attribute');
    $routes->import('../src/Schema/Presentation/Http/', 'attribute');
    $routes->import('../src/Taxonomy/Presentation/Http/', 'attribute');
    $routes->import('../src/Settings/Presentation/Http/', 'attribute');
    $routes->import('../src/Theme/Presentation/Http/', 'attribute');
    $routes->import('../src/Extensions/Presentation/Http/', 'attribute');
};
