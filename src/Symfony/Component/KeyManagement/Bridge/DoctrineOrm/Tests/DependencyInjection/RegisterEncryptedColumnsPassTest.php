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
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection\RegisterEncryptedColumnsPass;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\EncryptedColumnListener;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\EncryptedColumnsAddress;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\EncryptedColumnsEntity;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\PlainEntity;

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
        ], $container->getParameter('.key_management.doctrine_dbal.types'));
        $this->assertSame(['person.contact' => [
            'string' => 'encrypted_string@person.contact',
            'text' => 'encrypted_text@person.contact',
        ]], $container->getDefinition('key_management.encrypted_column_listener')->getArgument(0));
    }

    public function testTheClassesOfTheColumnsOfEachRegisteredTypeAreKept()
    {
        $container = $this->createContainer(['speaker_contact' => ['type' => 'string', 'key' => 'person.contact']]);
        $container->register('.abstract.person', EncryptedColumnsEntity::class)
            ->addResourceTag('key_management.encrypted_column', ['key' => 'person.contact', 'type' => 'string'])
            ->addResourceTag('key_management.encrypted_column', ['key' => 'person.contact', 'type' => 'text'])
            ->addResourceTag('key_management.encrypted_column', ['key' => 'person.email', 'type' => 'text']);
        $container->register('.abstract.address', EncryptedColumnsAddress::class)
            ->addResourceTag('key_management.encrypted_column', ['key' => 'person.contact', 'type' => 'text'])
            ->addResourceTag('key_management.encrypted_column', ['key' => 'person.contact', 'type' => 'text']);

        (new RegisterEncryptedColumnsPass())->process($container);

        $this->assertSame([
            'encrypted_text@person.contact' => [EncryptedColumnsEntity::class, EncryptedColumnsAddress::class],
            'encrypted_text@person.email' => [EncryptedColumnsEntity::class],
        ], $container->getParameter('.key_management.encrypted_column_classes'), 'a configured type is not tied to the classes of its columns.');
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
        ], $container->getParameter('.key_management.doctrine_dbal.types'));
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

    public function testNoTypeIsAddedWithoutEncryptedColumns()
    {
        $container = $this->createContainer([]);

        (new RegisterEncryptedColumnsPass())->process($container);

        $this->assertSame([], $container->getParameter('.key_management.doctrine_dbal.types'));
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
        $container->setParameter('.key_management.doctrine_dbal.types', []);

        (new RegisterEncryptedColumnsPass())->process($container);

        $this->assertFalse($container->hasParameter('.key_management.encrypted_column_classes'));
    }

    private function createContainer(?array $types): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('key_management.encrypted_column_listener', EncryptedColumnListener::class)->setArguments([[]]);

        if (null !== $types) {
            $container->setParameter('.key_management.doctrine_dbal.types', $types);
        }

        return $container;
    }
}
