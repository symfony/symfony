<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\DependencyInjection;

use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\ORM\Mapping\Entity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedTypes;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection\RegisterEncryptedColumnsPass;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\EncryptedColumnListener;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\AutomaticRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\EncryptedColumnsAddress;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\EncryptedColumnsEntity;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\PlainEntity;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;

class RegisterEncryptedColumnsPassTest extends TestCase
{
    public function testAClassIsTaggedWithTheKeyAndTheTypeOfEachEncryptedColumn()
    {
        $definition = new ChildDefinition('');

        RegisterEncryptedColumnsPass::autoconfigure($definition, new Entity(), new \ReflectionClass(EncryptedColumnsEntity::class));

        $this->assertSame([
            ['key' => 'person.contact', 'type' => 'string'],
            ['key' => 'person.contact', 'type' => 'text'],
            ['key' => 'person.contact', 'type' => 'datetime_immutable'],
            ['key' => 'person.email', 'type' => 'string'],
        ], $definition->getTag('key_management.encrypted_column'));
        $this->assertTrue($definition->hasTag('container.excluded'), 'an entity is a resource, not a service.');
    }

    public function testAnEmbeddableIsTaggedLikeAnEntity()
    {
        $definition = new ChildDefinition('');

        RegisterEncryptedColumnsPass::autoconfigure($definition, new Embeddable(), new \ReflectionClass(EncryptedColumnsAddress::class));

        $this->assertSame([['key' => 'person.address', 'type' => 'string']], $definition->getTag('key_management.encrypted_column'));
    }

    public function testAClassWithoutEncryptedColumnIsLeftAlone()
    {
        $definition = new ChildDefinition('');

        RegisterEncryptedColumnsPass::autoconfigure($definition, new Entity(), new \ReflectionClass(PlainEntity::class));

        $this->assertSame([], $definition->getTags());
    }

    public function testEachKeyAndTypeGetsOneEncryptedType()
    {
        $container = $this->createContainer([]);
        $container->register('.abstract.person', EncryptedColumnsEntity::class)
            ->addResourceTag('key_management.encrypted_column', ['key' => 'person.contact', 'type' => 'string'])
            ->addResourceTag('key_management.encrypted_column', ['key' => 'person.contact', 'type' => 'text']);
        $container->register('.abstract.address', EncryptedColumnsAddress::class)
            ->addResourceTag('key_management.encrypted_column', ['key' => 'person.contact', 'type' => 'string']);

        (new RegisterEncryptedColumnsPass())->process($container);

        $this->assertSame([
            'encrypted_string@person.contact' => ['type' => 'string', 'key' => 'person.contact'],
            'encrypted_text@person.contact' => ['type' => 'text', 'key' => 'person.contact'],
        ], $container->getDefinition('key_management.doctrine_dbal.encrypted_types')->getArgument(1));
        $this->assertSame(['person.contact' => [
            'string' => 'encrypted_string@person.contact',
            'text' => 'encrypted_text@person.contact',
        ]], $container->getDefinition('key_management.encrypted_column_listener')->getArgument(0));
    }

    public function testAConfiguredTypeServesTheColumnsOfItsKeyAndType()
    {
        $container = $this->createContainer(['speaker_contact' => ['type' => 'string', 'key' => 'person.contact']]);
        $container->register('.abstract.person', EncryptedColumnsEntity::class)
            ->addResourceTag('key_management.encrypted_column', ['key' => 'person.contact', 'type' => 'string'])
            ->addResourceTag('key_management.encrypted_column', ['key' => 'person.contact', 'type' => 'text']);

        (new RegisterEncryptedColumnsPass())->process($container);

        $this->assertSame([
            'speaker_contact' => ['type' => 'string', 'key' => 'person.contact'],
            'encrypted_text@person.contact' => ['type' => 'text', 'key' => 'person.contact'],
        ], $container->getDefinition('key_management.doctrine_dbal.encrypted_types')->getArgument(1));
        $this->assertSame(['person.contact' => [
            'string' => 'speaker_contact',
            'text' => 'encrypted_text@person.contact',
        ]], $container->getDefinition('key_management.encrypted_column_listener')->getArgument(0));
    }

    public function testTheConfiguredTypesAreKnownToTheListenerWithoutAnyEncryptedColumn()
    {
        $container = $this->createContainer(['speaker_contact' => ['type' => 'string', 'key' => 'person.contact']]);

        (new RegisterEncryptedColumnsPass())->process($container);

        $this->assertSame(['person.contact' => ['string' => 'speaker_contact']], $container->getDefinition('key_management.encrypted_column_listener')->getArgument(0));
    }

    public function testTheEncryptedTypesGoWhenNothingNeedsThem()
    {
        $container = $this->createContainer([]);

        (new RegisterEncryptedColumnsPass())->process($container);

        $this->assertFalse($container->hasDefinition('key_management.doctrine_dbal.encrypted_types'));
        $this->assertTrue($container->hasDefinition('key_management.encrypted_column_listener'), 'the listener reports an encrypted column the container did not find.');
    }

    public function testAnEncryptedColumnNeedsAnEnvelopeEncrypter()
    {
        $container = $this->createContainer(null);
        $container->register('.abstract.person', EncryptedColumnsEntity::class)
            ->addResourceTag('key_management.encrypted_column', ['key' => 'person.contact', 'type' => 'string']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The encrypted columns of "%s" need an envelope encrypter: configure "key_management.store", or set "key_management.default_client".', EncryptedColumnsEntity::class));

        (new RegisterEncryptedColumnsPass())->process($container);
    }

    public function testAConfiguredTypeCannotTakeTheNameOfAnotherOne()
    {
        $container = $this->createContainer(['encrypted_text@person.contact' => ['type' => 'string', 'key' => 'person.contact']]);
        $container->register('.abstract.person', EncryptedColumnsEntity::class)
            ->addResourceTag('key_management.encrypted_column', ['key' => 'person.contact', 'type' => 'text']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The encrypted type "encrypted_text@person.contact" is configured to wrap the "string" type under the key "person.contact", but that name is the one given to the encrypted columns of type "text" under that key. Rename it.');

        (new RegisterEncryptedColumnsPass())->process($container);
    }

    public function testNothingHappensWithoutTheListener()
    {
        $container = new ContainerBuilder();
        $container->register('key_management.doctrine_dbal.encrypted_types', EncryptedTypes::class)->setArguments([new Reference('app.envelopes'), []]);

        (new RegisterEncryptedColumnsPass())->process($container);

        $this->assertTrue($container->hasDefinition('key_management.doctrine_dbal.encrypted_types'));
    }

    public function testAnOmittedKeyNeedsAStoreEvenWithADefaultEncrypter()
    {
        $container = $this->createContainer([]);
        $container->register('.abstract.person', EncryptedColumnsEntity::class)
            ->addResourceTag('key_management.encrypted_column', ['key' => null, 'type' => 'string']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('omits its key and needs a data key store');

        (new RegisterEncryptedColumnsPass())->process($container);
    }

    public function testAutomaticColumnsConfigureTheEntityManagers()
    {
        $container = $this->createContainer([]);
        $container->register('key_management.stored_envelope_encrypter', StoredEnvelopeEncrypter::class);
        $container->register('.abstract.person', EncryptedColumnsEntity::class)
            ->addResourceTag('key_management.encrypted_column', ['key' => null, 'type' => 'string']);
        $container->register('app.manager', \Doctrine\ORM\EntityManager::class)->setConfigurator('app_configure_manager');
        $container->setParameter('doctrine.entity_managers', ['default' => 'app.manager']);

        (new RegisterEncryptedColumnsPass())->process($container);

        $this->assertNotSame('app_configure_manager', $container->getDefinition('app.manager')->getConfigurator());
        $this->assertFalse($container->hasDefinition('key_management.doctrine_dbal.encrypted_types'));
        $this->assertSame([], $container->getDefinition('key_management.encrypted_column_listener')->getArgument(0));
        $this->assertNotNull($container->getDefinition('key_management.encrypted_column_listener')->getArgument(1));
    }

    public function testTheCompiledConfiguratorPreservesThePreviousCallback()
    {
        $container = $this->createContainer([]);
        $container->register('key_management.stored_envelope_encrypter', StoredEnvelopeEncrypter::class)
            ->setArguments([new Definition(InMemoryDataKeyStore::class)]);
        $container->register('.abstract.person', AutomaticRecord::class)
            ->addResourceTag('key_management.encrypted_column', ['key' => null, 'type' => 'string']);
        $events = (new Definition(\Doctrine\Common\EventManager::class))
            ->addMethodCall('addEventListener', [[\Doctrine\ORM\Events::loadClassMetadata], new Reference('key_management.encrypted_column_listener')]);
        $configuration = (new Definition(\Doctrine\ORM\Configuration::class))
            ->setFactory([\Doctrine\ORM\ORMSetup::class, 'createConfiguration'])
            ->setArguments([true])
            ->addMethodCall('setMetadataDriverImpl', [(new Definition(\Doctrine\ORM\Mapping\Driver\AttributeDriver::class))->setArguments([[], true])])
            ->addMethodCall('enableNativeLazyObjects', [true]);
        $connection = (new Definition(\Doctrine\DBAL\Connection::class))
            ->setFactory([\Doctrine\DBAL\DriverManager::class, 'getConnection'])
            ->setArguments([['driver' => 'pdo_sqlite', 'memory' => true]]);
        $container->register('app.manager', \Doctrine\ORM\EntityManager::class)
            ->setArguments([$connection, $configuration, $events])
            ->setConfigurator([self::class, 'configureAutomaticManager'])
            ->setPublic(true);
        $container->setParameter('doctrine.entity_managers', ['default' => 'app.manager']);
        (new RegisterEncryptedColumnsPass())->process($container);
        $container->compile();
        eval('?>'.(new PhpDumper($container))->dump(['class' => 'AutomaticEncryptedColumnContainer']));

        $manager = (new \AutomaticEncryptedColumnContainer())->get('app.manager');
        $configuration = $manager->getConnection()->getConfiguration();
        $registry = method_exists($configuration, 'getTypeProvider') ? $configuration->getTypeProvider() : \Doctrine\DBAL\Types\Type::getTypeRegistry();
        $type = $registry->get($manager->getClassMetadata(AutomaticRecord::class)->getTypeOfField('first'));
        $platform = $manager->getConnection()->getDatabasePlatform();
        $written = $type->convertToDatabaseValue('a note', $platform);

        $this->assertInstanceOf(Envelope::class, Envelope::fromBytes($written));
        $this->assertSame('a note', $type->convertToPHPValue($written, $platform));
    }

    public static function configureAutomaticManager(\Doctrine\ORM\EntityManagerInterface $manager): void
    {
        $manager->getConfiguration()->setMetadataDriverImpl(new class([], true) extends \Doctrine\ORM\Mapping\Driver\AttributeDriver {
            public function getAllClassNames(): array
            {
                return [AutomaticRecord::class];
            }
        });
    }

    private function createContainer(?array $types): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('key_management.encrypted_column_listener', EncryptedColumnListener::class)->setArguments([[]]);

        if (null !== $types) {
            $container->register('key_management.doctrine_dbal.encrypted_types', EncryptedTypes::class)->setArguments([new Reference('app.envelopes'), $types]);
        }

        return $container;
    }
}
