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

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\DefaultNamingStrategy;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\NamingStrategy;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedTypes;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection\ConfigureEncryptedColumns;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\EncryptedColumnListener;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\AutomaticRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\DottedColumnRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\DottedSchemaRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\DottedTableRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\ExplicitRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\InheritedRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\JoinedChild;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\JoinedRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\MutatedRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\NamedRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\OtherInheritedRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\OtherRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\PhysicalNamedRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\PrefixRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\RenamedRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\SingleChild;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\SingleRecord;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\Automatic\SingleRecordAlias;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Test\InMemoryDataKeyStore;

#[RequiresPhpExtension('pdo_sqlite')]
class AutomaticEncryptedColumnTest extends TestCase
{
    private Connection $connection;
    private InMemoryDataKeyStore $store;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->store = new InMemoryDataKeyStore();
    }

    public function testAnOmittedKeyEncryptsTheMappedColumn()
    {
        $manager = $this->manager();
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(AutomaticRecord::class)]);
        $record = new AutomaticRecord();
        $record->first = 'first secret';
        $record->second = 'second secret';
        $manager->persist($record);
        $manager->flush();
        $manager->clear();

        $stored = $this->connection->fetchAssociative('SELECT first_value, second_value FROM automatic_record');
        $this->assertInstanceOf(Envelope::class, Envelope::fromBytes($stored['first_value']));
        $this->assertInstanceOf(Envelope::class, Envelope::fromBytes($stored['second_value']));
        $this->assertSame('first secret', $manager->find(AutomaticRecord::class, 1)->first);
        $this->assertSame('second secret', $manager->find(AutomaticRecord::class, 1)->second);
    }

    public function testValuesCopiedBetweenColumnsAreRefused()
    {
        $manager = $this->manager();
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(AutomaticRecord::class)]);
        $record = new AutomaticRecord();
        $record->first = 'first secret';
        $record->second = 'second secret';
        $manager->persist($record);
        $manager->flush();
        $manager->clear();
        $this->connection->executeStatement('UPDATE automatic_record SET first_value = second_value');

        $this->expectException(DecryptionFailedException::class);
        $manager->find(AutomaticRecord::class, 1);
    }

    public function testRenamingTheEntityAndPropertiesKeepsValuesReadable()
    {
        $manager = $this->manager();
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(AutomaticRecord::class)]);
        $record = new AutomaticRecord();
        $record->first = 'first secret';
        $record->second = 'second secret';
        $manager->persist($record);
        $manager->flush();
        $this->store->forget();

        $reader = $this->manager([RenamedRecord::class]);
        $renamed = $reader->find(RenamedRecord::class, 1);

        $this->assertSame('first secret', $renamed->renamedFirst);
        $this->assertSame('second secret', $renamed->renamedSecond);
    }

    public function testAnExplicitKeyCanRetainTheExistingAutomaticScope()
    {
        $manager = $this->manager();
        $metadata = $manager->getClassMetadata(AutomaticRecord::class);
        (new SchemaTool($manager))->createSchema([$metadata]);
        $record = new AutomaticRecord();
        $record->first = 'first secret';
        $manager->persist($record);
        $manager->flush();
        $this->store->forget();
        $scope = 'orm:'.hash('sha256', serialize([$metadata->getSchemaName(), $metadata->getTableName(), $metadata->getColumnName('first')]));
        $name = 'automatic_test_retained_scope';
        (new EncryptedTypes(new StoredEnvelopeEncrypter($this->store), [$name => ['type' => 'string', 'key' => $scope]]))->register($this->connection);

        $written = $this->connection->fetchOne('SELECT first_value FROM automatic_record');

        $this->assertSame('first secret', $this->registry()->get($name)->convertToPHPValue($written, $this->connection->getDatabasePlatform()));
    }

    public function testTheSameColumnInAnotherTableRefusesCopiedValues()
    {
        $manager = $this->manager([AutomaticRecord::class, OtherRecord::class]);
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(AutomaticRecord::class), $manager->getClassMetadata(OtherRecord::class)]);
        $record = new AutomaticRecord();
        $record->first = 'first secret';
        $other = new OtherRecord();
        $other->first = 'other secret';
        $manager->persist($record);
        $manager->persist($other);
        $manager->flush();
        $manager->clear();
        $this->connection->executeStatement('UPDATE other_record SET first_value = (SELECT first_value FROM automatic_record)');

        $this->expectException(DecryptionFailedException::class);
        $manager->find(OtherRecord::class, 1);
    }

    public function testNamingStrategiesAndEmbeddedPrefixesUsePhysicalNames()
    {
        $manager = $this->manager([NamedRecord::class], null, new UnderscoreNamingStrategy(\CASE_LOWER));
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(NamedRecord::class)]);
        $record = new NamedRecord();
        $record->privateValue = 'private secret';
        $record->delivery->postalCode = 'delivery secret';
        $record->billing->postalCode = 'billing secret';
        $manager->persist($record);
        $manager->flush();
        $this->store->forget();

        $reader = $this->manager([PhysicalNamedRecord::class]);
        $physical = $reader->find(PhysicalNamedRecord::class, 1);

        $this->assertSame('private secret', $physical->secret);
        $this->assertSame('delivery secret', $physical->deliverySecret);
        $this->assertSame('billing secret', $physical->billingSecret);
    }

    public function testValuesCopiedBetweenEmbeddedPrefixesAreRefused()
    {
        $manager = $this->manager([NamedRecord::class], null, new UnderscoreNamingStrategy(\CASE_LOWER));
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(NamedRecord::class)]);
        $record = new NamedRecord();
        $record->delivery->postalCode = 'delivery secret';
        $record->billing->postalCode = 'billing secret';
        $manager->persist($record);
        $manager->flush();
        $manager->clear();
        $this->connection->executeStatement('UPDATE named_record SET delivery_postal_code = billing_postal_code');

        $this->expectException(DecryptionFailedException::class);
        $manager->find(NamedRecord::class, 1);
    }

    public function testAMappedSuperclassUsesEachConcreteTable()
    {
        $manager = $this->manager([InheritedRecord::class, OtherInheritedRecord::class]);
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(InheritedRecord::class), $manager->getClassMetadata(OtherInheritedRecord::class)]);
        $record = new InheritedRecord();
        $record->inherited = 'first secret';
        $other = new OtherInheritedRecord();
        $other->inherited = 'other secret';
        $manager->persist($record);
        $manager->persist($other);
        $manager->flush();
        $manager->clear();

        $this->assertSame('first secret', $manager->find(InheritedRecord::class, 1)->inherited);
        $this->assertSame('other secret', $manager->find(OtherInheritedRecord::class, 1)->inherited);
        $manager->clear();
        $this->connection->executeStatement('UPDATE other_inherited_record SET inherited_value = (SELECT inherited_value FROM inherited_record)');

        $this->expectException(DecryptionFailedException::class);
        $manager->find(OtherInheritedRecord::class, 1);
    }

    public function testJoinedInheritanceUsesTheTableStoringEachColumn()
    {
        $manager = $this->manager([JoinedRecord::class, JoinedChild::class]);
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(JoinedRecord::class), $manager->getClassMetadata(JoinedChild::class)]);
        $record = new JoinedChild();
        $record->rootValue = 'root secret';
        $record->childValue = 'child secret';
        $manager->persist($record);
        $manager->flush();
        $manager->clear();
        $read = $manager->find(JoinedChild::class, 1);

        $this->assertSame('root secret', $read->rootValue);
        $this->assertSame('child secret', $read->childValue);
        $stored = $this->connection->fetchOne('SELECT root_value FROM joined_record');
        $this->assertSame('root secret', $this->type($manager, JoinedRecord::class, 'rootValue')->convertToPHPValue($stored, $this->connection->getDatabasePlatform()));
        $this->assertSame('root secret', $this->type($manager, JoinedChild::class, 'rootValue')->convertToPHPValue($stored, $this->connection->getDatabasePlatform()));
        $manager->clear();
        $this->connection->executeStatement('UPDATE joined_child SET child_value = (SELECT root_value FROM joined_record)');

        $this->expectException(DecryptionFailedException::class);
        $manager->find(JoinedChild::class, 1);
    }

    public function testSingleTableInheritanceUsesTheRootTableForChildColumns()
    {
        $manager = $this->manager([SingleRecord::class, SingleChild::class]);
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(SingleRecord::class), $manager->getClassMetadata(SingleChild::class)]);
        $record = new SingleChild();
        $record->rootValue = 'root secret';
        $record->childValue = 'child secret';
        $manager->persist($record);
        $manager->flush();
        $manager->clear();
        $this->assertSame('child secret', $manager->find(SingleChild::class, 1)->childValue);
        $this->store->forget();

        $reader = $this->manager([SingleRecordAlias::class]);

        $this->assertSame('child secret', $reader->find(SingleRecordAlias::class, 1)->secret);
    }

    #[TestWith([DottedColumnRecord::class, DottedTableRecord::class])]
    #[TestWith([DottedColumnRecord::class, DottedSchemaRecord::class])]
    #[TestWith([DottedTableRecord::class, DottedSchemaRecord::class])]
    public function testDotsDoNotMergeSchemaTableAndColumnNames(string $first, string $second)
    {
        $strategy = new class extends DefaultNamingStrategy {
            public function classToTableName(string $className): string
            {
                return DottedTableRecord::class === $className ? 'users.email' : parent::classToTableName($className);
            }
        };
        $manager = $this->manager([$first, $second], null, $strategy);
        $platform = $this->connection->getDatabasePlatform();
        $stored = $this->type($manager, $first, 'secret')->convertToDatabaseValue('a secret', $platform);

        $this->expectException(DecryptionFailedException::class);
        $this->type($manager, $second, 'secret')->convertToPHPValue($stored, $platform);
    }

    #[TestWith([false], 'global registry')]
    #[TestWith([true], 'connection registry')]
    public function testAWarmSerializedCacheRegistersTypesInAFreshRegistry(bool $connectionRegistry)
    {
        if ($connectionRegistry && !method_exists($this->connection->getConfiguration(), 'setTypeProvider')) {
            $this->markTestSkipped('doctrine/dbal < 4.6 cannot replace the type provider of a connection.');
        }

        $cache = new ArrayAdapter(0, true);
        $manager = $this->manager([AutomaticRecord::class], $cache);
        $metadata = $manager->getClassMetadata(AutomaticRecord::class);
        (new SchemaTool($manager))->createSchema([$metadata]);
        $record = new AutomaticRecord();
        $record->first = 'first secret';
        $record->second = 'second secret';
        $manager->persist($record);
        $manager->flush();
        $manager->clear();
        if ($connectionRegistry) {
            $registry = new TypeRegistry();
            $this->connection->getConfiguration()->setTypeProvider($registry);
            $this->assertFalse($registry->has($metadata->getTypeOfField('first')));
        } else {
            foreach ($metadata->fieldMappings as $mapping) {
                if ('id' === $mapping->fieldName) {
                    continue;
                }

                $this->registry()->override($mapping->type, new class extends StringType {
                    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
                    {
                        throw new \LogicException('A stale automatic type was reused.');
                    }
                });
            }
        }
        $this->store->forget();

        $reader = $this->manager([AutomaticRecord::class], $cache, null, null, true);
        $this->assertNotSame($metadata, $reader->getClassMetadata(AutomaticRecord::class));
        $this->assertSame('first secret', $reader->find(AutomaticRecord::class, 1)->first);
        $this->assertSame('second secret', $reader->find(AutomaticRecord::class, 1)->second);
        $reader->clear();
        $this->connection->executeStatement('UPDATE automatic_record SET first_value = second_value');

        $this->expectException(DecryptionFailedException::class);
        $reader->find(AutomaticRecord::class, 1);
    }

    #[TestWith([false], 'global registry')]
    #[TestWith([true], 'connection registry')]
    public function testCachedTypesAreRegisteredWithoutLoadingMetadata(bool $connectionRegistry)
    {
        if ($connectionRegistry && !method_exists($this->connection->getConfiguration(), 'setTypeProvider')) {
            $this->markTestSkipped('doctrine/dbal < 4.6 cannot replace the type provider of a connection.');
        }

        $metadataCache = new ArrayAdapter(0, true);
        $queryCache = new ArrayAdapter(0, true);
        $typesCache = new ArrayAdapter(0, true);
        $dql = 'SELECT r.first FROM '.AutomaticRecord::class.' r';
        $manager = $this->manager([AutomaticRecord::class], $metadataCache, null, new EncryptedColumnListener([], new StoredEnvelopeEncrypter($this->store), $typesCache));
        $manager->getConfiguration()->setQueryCache($queryCache);
        $metadata = $manager->getClassMetadata(AutomaticRecord::class);
        (new SchemaTool($manager))->createSchema([$metadata]);
        $record = new AutomaticRecord();
        $record->first = 'first secret';
        $manager->persist($record);
        $manager->flush();
        $this->assertSame([['first' => 'first secret']], $manager->createQuery($dql)->getArrayResult());
        if ($connectionRegistry) {
            $this->connection->getConfiguration()->setTypeProvider(new TypeRegistry());
        } else {
            $this->registry()->override($metadata->getTypeOfField('first'), new StringType());
        }
        $this->store->forget();

        $reader = $this->manager([AutomaticRecord::class], $metadataCache, null, new EncryptedColumnListener([], new StoredEnvelopeEncrypter($this->store), $typesCache), true);
        $reader->getConfiguration()->setQueryCache($queryCache);

        $this->assertSame([], $reader->getMetadataFactory()->getLoadedMetadata());
        $this->assertSame([['first' => 'first secret']], $reader->createQuery($dql)->getArrayResult());
        $this->assertSame([], $reader->getMetadataFactory()->getLoadedMetadata());
        $this->assertSame('first secret', $reader->find(AutomaticRecord::class, 1)->first);
    }

    #[TestWith([false], 'without a types cache')]
    #[TestWith([true], 'with a types cache')]
    public function testFindingTheTypesLeavesTheMetadataOfTheManagerUnloaded(bool $typesCache)
    {
        $listener = new EncryptedColumnListener([], new StoredEnvelopeEncrypter($this->store), $typesCache ? new ArrayAdapter(0, true) : null);
        $manager = $this->manager([JoinedRecord::class, JoinedChild::class], new ArrayAdapter(0, true), null, $listener);

        $this->assertSame([], $manager->getMetadataFactory()->getLoadedMetadata());

        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(JoinedRecord::class), $manager->getClassMetadata(JoinedChild::class)]);
        $record = new JoinedChild();
        $record->rootValue = 'root secret';
        $record->childValue = 'child secret';
        $manager->persist($record);
        $manager->flush();
        $manager->clear();
        $read = $manager->find(JoinedChild::class, 1);

        $this->assertSame('root secret', $read->rootValue);
        $this->assertSame('child secret', $read->childValue);
    }

    public function testAListenerRenamingColumnsAfterThisOneKeepsValuesReadableFromWarmCaches()
    {
        $metadataCache = new ArrayAdapter(0, true);
        $typesCache = new ArrayAdapter(0, true);
        $writer = $this->renamingManager($metadataCache, $typesCache, false);
        (new SchemaTool($writer))->createSchema([$writer->getClassMetadata(AutomaticRecord::class)]);
        $record = new AutomaticRecord();
        $record->first = 'first secret';
        $record->second = 'second secret';
        $writer->persist($record);
        $writer->flush();
        $this->store->forget();

        $reader = $this->renamingManager($metadataCache, $typesCache, false);

        $this->assertSame('first secret', $reader->find(AutomaticRecord::class, 1)->first);
        $this->assertSame('second secret', $this->manager([MutatedRecord::class])->find(MutatedRecord::class, 1)->second);
    }

    public function testAListenerRenamingColumnsBeforeThisOneLetsTheTypesBeCached()
    {
        $metadataCache = new ArrayAdapter(0, true);
        $typesCache = new ArrayAdapter(0, true);
        $writer = $this->renamingManager($metadataCache, $typesCache, true);
        (new SchemaTool($writer))->createSchema([$writer->getClassMetadata(AutomaticRecord::class)]);
        $record = new AutomaticRecord();
        $record->first = 'first secret';
        $record->second = 'second secret';
        $writer->persist($record);
        $writer->flush();
        $this->store->forget();

        $reader = $this->renamingManager($metadataCache, $typesCache, true);

        $this->assertSame([], $reader->getMetadataFactory()->getLoadedMetadata());
        $this->assertSame('first secret', $reader->find(AutomaticRecord::class, 1)->first);
        $this->assertSame('second secret', $this->manager([MutatedRecord::class])->find(MutatedRecord::class, 1)->second);
    }

    public function testOmittingTheKeyWithoutAStoreExplainsTheMissingConfiguration()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('needs a data key store');

        $this->manager([AutomaticRecord::class], null, null, new EncryptedColumnListener([]));
    }

    public function testAnExplicitZeroKeyCanBeUsedBesideAnAutomaticColumn()
    {
        if (method_exists($this->connection->getConfiguration(), 'setTypeProvider')) {
            $this->connection->getConfiguration()->setTypeProvider(new TypeRegistry());
        }

        $registry = $this->registry();
        $name = 'automatic_test_explicit_zero';
        $envelopes = new StoredEnvelopeEncrypter($this->store);
        (new EncryptedTypes($envelopes, [$name => ['type' => 'string', 'key' => '0']]))->register($this->connection);
        $type = $registry->get($name);
        $listener = new EncryptedColumnListener(['0' => ['string' => $name]], $envelopes);
        $manager = $this->manager([ExplicitRecord::class], null, null, $listener);
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(ExplicitRecord::class)]);
        $record = new ExplicitRecord();
        $record->secret = 'explicit secret';
        $record->automatic = 'automatic secret';
        $manager->persist($record);
        $manager->flush();
        $manager->clear();

        $this->assertSame('explicit secret', $manager->find(ExplicitRecord::class, 1)->secret);
        $this->assertSame('automatic secret', $manager->find(ExplicitRecord::class, 1)->automatic);
        $this->assertSame('explicit secret', $type->convertToPHPValue($this->connection->fetchOne('SELECT secret FROM explicit_record'), $this->connection->getDatabasePlatform()));
        $manager->clear();
        $this->connection->executeStatement('UPDATE explicit_record SET secret = automatic_secret');

        $this->expectException(DecryptionFailedException::class);
        $manager->find(ExplicitRecord::class, 1);
    }

    public function testAParentAliasStartingWithTheGeneratedPrefixIsStillOpaque()
    {
        $registry = $this->registry();
        $name = 'key_management_auto_v1_application_type';
        $registry->has($name) ? $registry->override($name, new StringType()) : $registry->register($name, new StringType());
        $manager = $this->manager([PrefixRecord::class]);
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(PrefixRecord::class)]);
        $record = new PrefixRecord();
        $record->secret = 'a secret';
        $manager->persist($record);
        $manager->flush();
        $manager->clear();

        $this->assertSame('a secret', $manager->find(PrefixRecord::class, 1)->secret);
    }

    public function testALaterMetadataListenerCanChangePhysicalNames()
    {
        $listener = new EncryptedColumnListener([], new StoredEnvelopeEncrypter($this->store));
        $events = new EventManager();
        $events->addEventListener(Events::loadClassMetadata, $listener);
        $events->addEventListener(Events::loadClassMetadata, new class {
            public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
            {
                $metadata = $event->getClassMetadata();
                if (AutomaticRecord::class !== $metadata->getName()) {
                    return;
                }

                $metadata->setPrimaryTable(['name' => 'mutated_record']);
                $metadata->setAttributeOverride('first', ['columnName' => 'renamed_first']);
                $metadata->setAttributeOverride('second', ['columnName' => 'renamed_second']);
            }
        });
        $manager = new EntityManager($this->connection, $this->configuration([AutomaticRecord::class]), $events);
        $listener->register($manager);
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(AutomaticRecord::class)]);
        $record = new AutomaticRecord();
        $record->first = 'first secret';
        $record->second = 'second secret';
        $manager->persist($record);
        $manager->flush();
        $this->store->forget();

        $reader = $this->manager([MutatedRecord::class]);
        $renamed = $reader->find(MutatedRecord::class, 1);

        $this->assertSame('first secret', $renamed->first);
        $this->assertSame('second secret', $renamed->second);
    }

    public function testTheConfiguratorRunsThePreviousCallbackBeforeRegisteringTypes()
    {
        $listener = new EncryptedColumnListener([], new StoredEnvelopeEncrypter($this->store));
        $events = new EventManager();
        $events->addEventListener(Events::loadClassMetadata, $listener);
        $manager = new EntityManager($this->connection, $this->configuration([AutomaticRecord::class]), $events);
        $called = false;
        $configure = new ConfigureEncryptedColumns($listener, 'default', function (EntityManagerInterface $configured) use ($manager, &$called): void {
            $this->assertSame($manager, $configured);
            $this->assertSame([], $configured->getMetadataFactory()->getLoadedMetadata());
            $called = true;
        });
        $configure($manager);
        $this->assertTrue($called);
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(AutomaticRecord::class)]);
        $record = new AutomaticRecord();
        $record->first = 'configured secret';
        $manager->persist($record);
        $manager->flush();
        $manager->clear();

        $this->assertSame('configured secret', $manager->find(AutomaticRecord::class, 1)->first);
    }

    private function manager(array $classes = [AutomaticRecord::class], ?ArrayAdapter $cache = null, ?NamingStrategy $strategy = null, ?EncryptedColumnListener $listener = null, bool $cacheOnly = false): EntityManagerInterface
    {
        $events = new EventManager();
        $listener ??= new EncryptedColumnListener([], new StoredEnvelopeEncrypter($this->store));
        $events->addEventListener(Events::loadClassMetadata, $listener);
        $manager = new EntityManager($this->connection, $this->configuration($classes, $cache, $strategy, $cacheOnly), $events);
        $listener->register($manager);

        return $manager;
    }

    private function renamingManager(ArrayAdapter $metadataCache, ArrayAdapter $typesCache, bool $renamesFirst): EntityManagerInterface
    {
        $listener = new EncryptedColumnListener([], new StoredEnvelopeEncrypter($this->store), $typesCache);
        $renamer = new class {
            public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
            {
                $metadata = $event->getClassMetadata();
                if (AutomaticRecord::class !== $metadata->getName()) {
                    return;
                }

                $metadata->setPrimaryTable(['name' => 'mutated_record']);
                $metadata->setAttributeOverride('first', ['columnName' => 'renamed_first']);
                $metadata->setAttributeOverride('second', ['columnName' => 'renamed_second']);
            }
        };
        $events = new EventManager();
        foreach ($renamesFirst ? [$renamer, $listener] : [$listener, $renamer] as $subscriber) {
            $events->addEventListener(Events::loadClassMetadata, $subscriber);
        }
        $manager = new EntityManager($this->connection, $this->configuration([AutomaticRecord::class], $metadataCache), $events);
        $listener->register($manager);

        return $manager;
    }

    private function configuration(array $classes, ?ArrayAdapter $cache = null, ?NamingStrategy $strategy = null, bool $cacheOnly = false): Configuration
    {
        $config = ORMSetup::createConfiguration(true);
        $config->setMetadataDriverImpl(new class($classes, $cacheOnly) extends AttributeDriver {
            public function __construct(private readonly array $classes, private readonly bool $cacheOnly)
            {
                parent::__construct([]);
            }

            public function getAllClassNames(): array
            {
                return $this->classes;
            }

            public function loadMetadataForClass(string $className, ClassMetadata $metadata): void
            {
                if ($this->cacheOnly) {
                    throw new \LogicException('The serialized metadata cache was not used.');
                }

                parent::loadMetadataForClass($className, $metadata);
            }
        });
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $config->enableNativeLazyObjects(true);
        if (null !== $cache) {
            $config->setMetadataCache($cache);
        }
        if (null !== $strategy) {
            $config->setNamingStrategy($strategy);
        }

        return $config;
    }

    private function type(EntityManagerInterface $manager, string $class, string $field): Type
    {
        return $this->registry()->get($manager->getClassMetadata($class)->getTypeOfField($field));
    }

    private function registry(): TypeRegistry
    {
        $configuration = $this->connection->getConfiguration();

        return method_exists($configuration, 'getTypeProvider') ? $configuration->getTypeProvider() : Type::getTypeRegistry();
    }
}
