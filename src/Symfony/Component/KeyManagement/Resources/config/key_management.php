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
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\BlindIndexListener;
use Symfony\Component\KeyManagement\Bridge\Flysystem\FlysystemKmsFactory;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\GoogleCloudKmsFactory;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\TransitKmsFactory;
use Symfony\Component\KeyManagement\Factory\FactoryRegistry;
use Symfony\Component\KeyManagement\Local\OpenSslKmsFactory;
use Symfony\Component\KeyManagement\Local\SealedBoxKmsFactory;
use Symfony\Component\KeyManagement\Local\SodiumKmsFactory;
use Symfony\Component\KeyManagement\Serializer\EnvelopeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

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
            ->tag('container.remove_if_missing', ['class' => FlysystemKmsFactory::class, 'package' => 'symfony/flysystem-key-management', 'parent_packages' => ['symfony/key-management']])

        ->set('key_management.factory.hashicorp_vault_transit', TransitKmsFactory::class)
            ->tag('key_management.factory')
            ->tag('container.remove_if_missing', ['class' => TransitKmsFactory::class, 'package' => 'symfony/hashicorp-vault-key-management', 'parent_packages' => ['symfony/key-management']])

        ->set('key_management.factory.aws_kms', AwsKmsFactory::class)
            ->tag('key_management.factory')
            ->tag('container.remove_if_missing', ['class' => AwsKmsFactory::class, 'package' => 'symfony/aws-key-management', 'parent_packages' => ['symfony/key-management']])

        ->set('key_management.factory.azure_key_vault', AzureKeyVaultFactory::class)
            ->tag('key_management.factory')
            ->tag('container.remove_if_missing', ['class' => AzureKeyVaultFactory::class, 'package' => 'symfony/azure-keyvault-key-management', 'parent_packages' => ['symfony/key-management']])

        ->set('key_management.factory.google_cloud_kms', GoogleCloudKmsFactory::class)
            ->tag('key_management.factory')
            ->tag('container.remove_if_missing', ['class' => GoogleCloudKmsFactory::class, 'package' => 'symfony/google-cloud-key-management', 'parent_packages' => ['symfony/key-management']])

        ->set('serializer.normalizer.key_management_envelope', EnvelopeNormalizer::class)
            ->tag('serializer.normalizer', ['built_in' => true, 'priority' => -880])
            ->tag('container.remove_if_missing', ['class' => DenormalizerInterface::class])

        // A blind index is written by hand next to the value it indexes, on every write path, and a
        // row whose tag was forgotten is a row no search returns. The listener fills the properties
        // an entity marks with #[BlindIndexed] instead; RegisterBlindIndexesPass hands it the
        // indexes the application registered, and removes it when there is none.
        ->set('key_management.blind_index_listener', BlindIndexListener::class)
            ->args([service_locator([])])
            ->tag('doctrine.event_listener', ['event' => 'onFlush'])
            ->tag('container.remove_if_missing', ['class' => BlindIndexListener::class, 'package' => 'symfony/doctrine-orm-key-management', 'parent_packages' => ['symfony/key-management']])
    ;
};
