<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $container) {
    $container->extension('framework', [
        'annotations' => false,
        'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
        'mailer' => [
            'smtp' => [
                'authenticators' => [
                    'my_authenticator_service1',
                    'my_authenticator_service2',
                ],
            ],
        ],
    ]);
};
