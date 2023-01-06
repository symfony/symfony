<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $container->extension('framework', [
        'http_method_override' => false,
        'mailer' => [
            'dsn' => 'smtp://example.com',
            'envelope' => [
                'sender' => 'sender@example.org',
                'recipients' => ['redirected@example.org', 'redirected1@example.org'],
            ],
            'headers' => [
                'from' => 'from@example.org',
                'bcc' => ['bcc1@example.org', 'bcc2@example.org'],
                'foo' => 'bar',
            ],
        ],
    ]);
};
