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

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Fixtures\Mailer\RecipientFetcher;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set(RecipientFetcher::class)
        ->public();

    $container->extension('framework', [
        'http_method_override' => false,
        'handle_all_throwables' => true,
        'php_errors' => ['log' => true],
        'mailer' => [
            'dsn' => 'smtp://example.com',
            'envelope' => [
                'sender' => 'sender@example.org',
                'recipient_fetcher' => RecipientFetcher::class,
            ],
            'headers' => [
                'from' => 'from@example.org',
                'bcc' => ['bcc1@example.org', 'bcc2@example.org'],
                'foo' => 'bar',
            ],
        ],
    ]);
};
