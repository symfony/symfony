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

use Symfony\Component\KeyManagement\Bridge\AwsKms\AwsKmsFactory;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\AzureKeyVaultFactory;
use Symfony\Component\KeyManagement\Bridge\Flysystem\FlysystemKmsFactory;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\GoogleCloudKmsFactory;
use Symfony\Component\KeyManagement\Bridge\Vault\TransitKmsFactory;
use Symfony\Component\KeyManagement\Factory\FactoryRegistry;
use Symfony\Component\KeyManagement\Local\OpenSslKmsFactory;
use Symfony\Component\KeyManagement\Local\SealedBoxKmsFactory;
use Symfony\Component\KeyManagement\Local\SodiumKmsFactory;
use Symfony\Component\KeyManagement\Serializer\EnvelopeNormalizer;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('key_management.factory', FactoryRegistry::class)
            ->args([tagged_iterator('key_management.factory')])

        ->set('key_management.factory.sodium', SodiumKmsFactory::class)
            ->tag('key_management.factory')

        ->set('key_management.factory.openssl', OpenSslKmsFactory::class)
            ->tag('key_management.factory')

        ->set('key_management.factory.sealed_box', SealedBoxKmsFactory::class)
            ->tag('key_management.factory')

        ->set('key_management.factory.flysystem', FlysystemKmsFactory::class)
            ->args([tagged_locator('key_management.flysystem', 'key')])
            ->tag('key_management.factory')

        ->set('key_management.factory.vault_transit', TransitKmsFactory::class)
            ->tag('key_management.factory')

        ->set('key_management.factory.aws_kms', AwsKmsFactory::class)
            ->tag('key_management.factory')

        ->set('key_management.factory.azure_key_vault', AzureKeyVaultFactory::class)
            ->tag('key_management.factory')

        ->set('key_management.factory.google_cloud_kms', GoogleCloudKmsFactory::class)
            ->tag('key_management.factory')

        ->set('serializer.normalizer.key_management_envelope', EnvelopeNormalizer::class)
            ->tag('serializer.normalizer', ['built_in' => true, 'priority' => -880])
    ;
};
