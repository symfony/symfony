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
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\BlindIndex;
use Symfony\Component\KeyManagement\BlindIndex\Email;
use Symfony\Component\KeyManagement\BlindIndex\EmailDomain;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\BlindIndexed;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\BlindIndexListener;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\BlindIndexedEntity;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\BlindIndexedNonStringEntity;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\BlindIndexedUnknownSourceEntity;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Tests\Fixtures\PlainEntity;
use Symfony\Component\KeyManagement\Test\InMemoryKms;

#[RequiresPhpExtension('pdo_sqlite')]
class BlindIndexListenerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Email $email;
    private EmailDomain $domain;

    protected function setUp(): void
    {
        $kms = new InMemoryKms();
        $wrappedKey = $kms->generateDataKey('app')->wrapped;

        $this->email = new Email($kms, $wrappedKey);
        $this->domain = new EmailDomain($kms, $wrappedKey);

        $config = ORMSetup::createConfiguration(true);
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/../Fixtures'], true));
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $config->enableNativeLazyObjects(true);

        $eventManager = new EventManager();
        $eventManager->addEventListener(Events::onFlush, new BlindIndexListener(new ServiceLocator([
            BlindIndex::class => static fn (): BlindIndex => new BlindIndex($kms, $wrappedKey),
            Email::class => fn (): Email => $this->email,
            EmailDomain::class => fn (): EmailDomain => $this->domain,
        ])));

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config, $eventManager);
        $this->entityManager = new EntityManager($connection, $config, $eventManager);

        (new SchemaTool($this->entityManager))->createSchema(array_map(
            $this->entityManager->getClassMetadata(...),
            [BlindIndexedEntity::class, BlindIndexedNonStringEntity::class, BlindIndexedUnknownSourceEntity::class, PlainEntity::class],
        ));
    }

    public function testTheTagsAreWrittenOnInsert()
    {
        $entity = (new BlindIndexedEntity())->setEmail('Ada@Example.ORG');

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->assertSame($this->email->of('Ada@Example.ORG'), $entity->getEmailIndex());
        $this->assertSame($this->domain->of('example.org'), $entity->getEmailDomainIndex());
        $this->assertSame([
            'email' => 'Ada@Example.ORG',
            'emailIndex' => $this->email->of('Ada@Example.ORG'),
            'emailDomainIndex' => $this->domain->of('example.org'),
        ], $this->row());
    }

    /**
     * The reason the listener runs on "onFlush" rather than on "prePersist": the value is set after
     * the entity is handed to the manager, which "prePersist" would have missed, leaving a row no
     * search ever returns.
     */
    public function testTheTagsAreWrittenWhateverTheOrderTheEntityIsFilledIn()
    {
        $entity = new BlindIndexedEntity();

        $this->entityManager->persist($entity);
        $entity->setEmail('ada@example.org');
        $this->entityManager->flush();

        $this->assertSame($this->email->of('ada@example.org'), $this->row()['emailIndex']);
    }

    public function testTheTagsFollowTheValueOnUpdate()
    {
        $entity = (new BlindIndexedEntity())->setEmail('ada@example.org');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $entity->setEmail('bob@example.com');
        $this->entityManager->flush();

        $this->assertSame([
            'email' => 'bob@example.com',
            'emailIndex' => $this->email->of('bob@example.com'),
            'emailDomainIndex' => $this->domain->of('example.com'),
        ], $this->row());
    }

    public function testAChangeOnAnotherPropertyLeavesTheTagsAlone()
    {
        $entity = (new BlindIndexedEntity())->setEmail('ada@example.org');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $entity->name = 'Ada';
        $this->entityManager->flush();

        $this->assertSame($this->email->of('ada@example.org'), $this->row()['emailIndex']);
    }

    /**
     * Which is what makes a row written before the index existed repair itself the next time it is
     * saved, and what keeps a hand-written tag from surviving.
     */
    public function testTheTagIsRederivedOverWhateverThePropertyHeld()
    {
        $entity = (new BlindIndexedEntity())->setEmail('ada@example.org')->setEmailIndex('stale');

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->assertSame($this->email->of('ada@example.org'), $this->row()['emailIndex']);
    }

    public function testANullValueGivesNoTag()
    {
        $entity = new BlindIndexedEntity();

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->assertSame(['email' => null, 'emailIndex' => null, 'emailDomainIndex' => null], $this->row());
    }

    public function testAnEntityCarryingNoIndexIsLeftAlone()
    {
        $entity = new PlainEntity();
        $entity->name = 'Ada';

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->assertSame('Ada', $this->entityManager->getConnection()->fetchOne('SELECT name FROM PlainEntity'));
    }

    public function testAnUnknownSourcePropertyIsRefused()
    {
        $this->entityManager->persist(new BlindIndexedUnknownSourceEntity());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The property "%1$s::$mail" is indexed by "%1$s::$emailIndex", but no such property is declared on that entity.', BlindIndexedUnknownSourceEntity::class));

        $this->entityManager->flush();
    }

    public function testAValueThatIsNotAStringIsRefused()
    {
        $entity = new BlindIndexedNonStringEntity();
        $entity->accountNumber = 42;
        $this->entityManager->persist($entity);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The property "%s::$accountNumber" holds a value of type "int", which "%s" cannot index: a blind index is derived from a string.', BlindIndexedNonStringEntity::class, BlindIndexed::class));

        $this->entityManager->flush();
    }

    public function testAnIndexThatIsNotRegisteredIsRefused()
    {
        $listener = new BlindIndexListener(new ServiceLocator([]));
        $this->entityManager->persist((new BlindIndexedEntity())->setEmail('ada@example.org'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('No blind index of class "%s" is registered, as "%s::$emailIndex" requires.', Email::class, BlindIndexedEntity::class));

        $listener->onFlush(new OnFlushEventArgs($this->entityManager));
    }

    /**
     * @return array<string, string|null>
     */
    private function row(): array
    {
        return $this->entityManager->getConnection()->fetchAssociative('SELECT email, emailIndex, emailDomainIndex FROM BlindIndexedEntity') ?: [];
    }
}
