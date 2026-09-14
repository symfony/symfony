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

use Symfony\Component\KeyManagement\Command\DecryptCommand;
use Symfony\Component\KeyManagement\Command\EncryptCommand;
use Symfony\Component\KeyManagement\Command\GenerateDataKeyCommand;
use Symfony\Component\KeyManagement\Command\RewrapDataKeysCommand;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('console.command.key_management_encrypt', EncryptCommand::class)
            ->args([
                tagged_locator('key_management.client', 'key'),
            ])
            ->tag('console.command')

        ->set('console.command.key_management_decrypt', DecryptCommand::class)
            ->args([
                tagged_locator('key_management.client', 'key'),
            ])
            ->tag('console.command')

        ->set('console.command.key_management_generate_data_key', GenerateDataKeyCommand::class)
            ->args([
                tagged_locator('key_management.client', 'key'),
            ])
            ->tag('console.command')

        ->set('console.command.key_management_rewrap_data_keys', RewrapDataKeysCommand::class)
            ->args([
                service('key_management.store')->nullOnInvalid(),
                tagged_locator('key_management.client', 'key'),
            ])
            ->tag('console.command')
    ;
};
