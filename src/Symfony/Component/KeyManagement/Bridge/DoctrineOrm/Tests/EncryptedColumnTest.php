<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\DataKeyStore;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedType;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\EncryptedColumnEntity;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryKms;

/**
 * The store-backed type as the ORM drives it: a flush is a transaction, and the row of a data key
 * minted during one that fails is rolled back with it.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class EncryptedColumnTest extends TestCase
{
    private Connection $connection;
    private Configuration $config;
    private InMemoryKms $kms;

    protected function setUp(): void
    {
        $this->kms = new InMemoryKms();
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $registry = Type::getTypeRegistry();
        $type = new EncryptedType(new StringType(), new StoredEnvelopeEncrypter($this->store()), 'user.email');
        $registry->has(EncryptedColumnEntity::TYPE) ? $registry->override(EncryptedColumnEntity::TYPE, $type) : $registry->register(EncryptedColumnEntity::TYPE, $type);

        $this->config = ORMSetup::createConfiguration(true);
        $this->config->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/Fixtures'], true));
        $this->config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $this->config->enableNativeLazyObjects(true);

        $manager = $this->manager();
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(EncryptedColumnEntity::class)]);
    }

    public function testAColumnWrittenAfterAFailedFlushIsReadableElsewhere()
    {
        $manager = $this->manager();
        $manager->persist(self::user('jane', 'jane@example.com'));
        $manager->persist(self::user('jane', 'duplicate@example.com'));

        try {
            $manager->flush();
            $this->fail('The unique constraint was expected to fail the flush.');
        } catch (UniqueConstraintViolationException) {
        }

        $this->assertSame(0, $this->keyRows(), 'the failed flush took its key row with it.');

        $manager = $this->manager();
        $manager->persist(self::user('john', 'john@example.com'));
        $manager->flush();

        $stored = $this->connection->fetchOne('SELECT email FROM EncryptedColumnEntity');
        $elsewhere = new StoredEnvelopeEncrypter($this->store());

        $this->assertSame('john@example.com', $elsewhere->decrypt(Envelope::fromBytes($stored)));
        $this->assertSame(1, $this->keyRows());
    }

    private function manager(): EntityManagerInterface
    {
        return new EntityManager($this->connection, $this->config);
    }

    /**
     * A store on the same connection, as another process, or a request served after the store was
     * reset, would have.
     */
    private function store(): DataKeyStore
    {
        $store = new DataKeyStore($this->connection, new ServiceLocator(['default' => fn (): InMemoryKms => $this->kms]), 'default', 'app');

        if (!$this->connection->createSchemaManager()->tablesExist([DataKeyStore::DEFAULT_TABLE])) {
            $store->createTable();
        }

        return $store;
    }

    private function keyRows(): int
    {
        return (int) $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s', DataKeyStore::DEFAULT_TABLE));
    }

    private static function user(string $username, string $email): EncryptedColumnEntity
    {
        $user = new EncryptedColumnEntity();
        $user->username = $username;
        $user->email = $email;

        return $user;
    }
}
