<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpKernel\EventListener\TestSessionListener;

return static function (ContainerConfigurator $container) {
    $container->parameters()->set('test.client.parameters', []);

    $container->services()
        ->set('test.client', KernelBrowser::class)
            ->args([
                service('kernel'),
                param('test.client.parameters'),
                service('test.client.history'),
                service('test.client.cookiejar'),
            ])
            ->share(false)
            ->public()

        ->set('test.client.history', History::class)->share(false)
        ->set('test.client.cookiejar', CookieJar::class)->share(false)

        ->set('test.session.listener', TestSessionListener::class)
            ->args([
                service_locator([
                    'session' => service('session')->ignoreOnInvalid(),
                ]),
            ])
            ->tag('kernel.event_subscriber')

        ->set('test.service_container', TestContainer::class)
            ->args([
                service('kernel'),
                'test.private_services_locator',
            ])
            ->public()

        ->set('test.private_services_locator', ServiceLocator::class)
            ->args([abstract_arg('callable collection')])
            ->public()
    ;
};
