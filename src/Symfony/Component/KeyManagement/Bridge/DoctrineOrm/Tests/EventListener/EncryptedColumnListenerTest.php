<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\EventListener;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedType;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\EncryptedColumnListener;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\EncryptedColumnsEntity;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\EncryptedUnmappedPropertyEntity;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryKms;

#[RequiresPhpExtension('pdo_sqlite')]
class EncryptedColumnListenerTest extends TestCase
{
    private const array TYPES = [
        'person.contact' => [
            'string' => 'key_management_test_contact',
            'text' => 'key_management_test_contact_text',
            'datetime_immutable' => 'key_management_test_contact_date',
        ],
        'person.email' => ['string' => 'key_management_test_email'],
        'person.address' => ['string' => 'key_management_test_address'],
    ];

    private Connection $connection;
    private Configuration $config;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $registry = method_exists($this->connection->getConfiguration(), 'getTypeProvider') ? $this->connection->getConfiguration()->getTypeProvider() : Type::getTypeRegistry();
        $envelopes = new EnvelopeEncrypter(new InMemoryKms());
        foreach (self::TYPES as $key => $types) {
            foreach ($types as $type => $name) {
                $encryptedType = new EncryptedType($registry->get($type), $envelopes, $key, $name);
                $registry->has($name) ? $registry->override($name, $encryptedType) : $registry->register($name, $encryptedType);
            }
        }

        $this->config = ORMSetup::createConfiguration(true);
        $this->config->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/../Fixtures'], true));
        $this->config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $this->config->enableNativeLazyObjects(true);
    }

    public function testAnEncryptedColumnTakesTheTypeOfItsKeyAndOfTheTypeItHadBefore()
    {
        $metadata = $this->entityManager()->getClassMetadata(EncryptedColumnsEntity::class);

        $this->assertSame('key_management_test_contact', $metadata->getTypeOfField('phone'));
        $this->assertSame('key_management_test_contact_text', $metadata->getTypeOfField('notes'));
        $this->assertSame('key_management_test_contact_date', $metadata->getTypeOfField('birthday'));
        $this->assertSame('key_management_test_email', $metadata->getTypeOfField('email'), 'a column of a mapped superclass is encrypted in the entities extending it.');
        $this->assertSame('key_management_test_address', $metadata->getTypeOfField('address.street'), 'a column of an embeddable is encrypted in the entities embedding it.');
        $this->assertSame('string', $metadata->getTypeOfField('name'));
    }

    public function testTheValuesAreEncryptedAtRestAndReadBack()
    {
        $entityManager = $this->entityManager();
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(EncryptedColumnsEntity::class)]);

        $person = (new EncryptedColumnsEntity())->setNotes('Prefers trains')->setEmail('ada@example.org');
        $person->phone = '+33 6 12 34 56 78';
        $person->birthday = new \DateTimeImmutable('1815-12-10 00:00:00');
        $person->address->street = '12 St James Square';
        $person->name = 'Ada';
        $entityManager->persist($person);
        $entityManager->flush();
        $entityManager->clear();

        $row = $this->connection->fetchAssociative('SELECT phone, notes, birthday, email, address_street, name FROM EncryptedColumnsEntity');
        foreach (['phone', 'notes', 'birthday', 'email', 'address_street'] as $column) {
            $this->assertInstanceOf(Envelope::class, Envelope::fromBytes($row[$column]), \sprintf('the "%s" column holds an envelope.', $column));
        }
        $this->assertSame('Ada', $row['name']);

        $person = $entityManager->find(EncryptedColumnsEntity::class, $person->id);

        $this->assertSame('+33 6 12 34 56 78', $person->phone);
        $this->assertSame('Prefers trains', $person->getNotes());
        $this->assertEquals(new \DateTimeImmutable('1815-12-10 00:00:00'), $person->birthday);
        $this->assertSame('ada@example.org', $person->getEmail());
        $this->assertSame('12 St James Square', $person->address->street);
    }

    public function testTheSchemaToolsDeclareABinaryColumnAndFindNothingToUpdateAfterwards()
    {
        $entityManager = $this->entityManager();
        $schemaTool = new SchemaTool($entityManager);
        $metadata = [$entityManager->getClassMetadata(EncryptedColumnsEntity::class)];

        $this->assertStringContainsString('phone BLOB DEFAULT NULL', implode("\n", $schemaTool->getCreateSchemaSql($metadata)));

        $schemaTool->createSchema($metadata);

        $this->assertSame([], $schemaTool->getUpdateSchemaSql($metadata));
    }

    public function testTheMetadataCacheKeepsTheEncryptedType()
    {
        $this->config->setMetadataCache(new ArrayAdapter());
        $this->entityManager()->getClassMetadata(EncryptedColumnsEntity::class);

        $withoutListener = new EntityManager($this->connection, $this->config, new EventManager());

        $this->assertSame('key_management_test_contact', $withoutListener->getClassMetadata(EncryptedColumnsEntity::class)->getTypeOfField('phone'));
    }

    public function testThePropertyMustBeAColumn()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The property "%s::$secret" has the "Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted" attribute but is not a mapped column. Only the value of a column can be encrypted.', EncryptedUnmappedPropertyEntity::class));

        $this->entityManager()->getClassMetadata(EncryptedUnmappedPropertyEntity::class);
    }

    public function testAColumnNeedsAnEncryptedTypeForItsKeyAndItsType()
    {
        $types = self::TYPES;
        unset($types['person.contact']['text']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('No encrypted type is registered for the key "person.contact" and the "text" type of "%s::$notes".', EncryptedColumnsEntity::class));

        $this->entityManager($types)->getClassMetadata(EncryptedColumnsEntity::class);
    }

    private function entityManager(array $types = self::TYPES): EntityManagerInterface
    {
        $eventManager = new EventManager();
        $eventManager->addEventListener(Events::loadClassMetadata, new EncryptedColumnListener($types));

        return new EntityManager($this->connection, $this->config, $eventManager);
    }
}
