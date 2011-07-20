<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Doctrine\Form\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bridge\Doctrine\Form\EventListener\EntityCollectionListener;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\Form;
use Symfony\Tests\Bridge\Doctrine\Fixtures\OwningEntityType;
use Symfony\Tests\Bridge\Doctrine\Form\DoctrineOrmTestCase;

/**
 * EntityCollectionListenerTest.
 *
 * @author Marc Weistroff <marc.weistroff@sensio.com>
 */
class EntityCollectionListenerTest extends DoctrineOrmTestCase
{
    const OWNING_ENTITY = 'Symfony\\Tests\\Bridge\\Doctrine\\Fixtures\\OwningEntity';
    const NON_OWNING_ENTITY = 'Symfony\\Tests\\Bridge\\Doctrine\\Fixtures\\NonOwningEntity';

    private $em;
    private $registry;

    protected function setUp()
    {
        parent::setUp();

        $this->em = self::createTestEntityManager();
        $this->registry = $this->createRegistryMock('default', $this->em);

        $schemaTool = new SchemaTool($this->em);
        $classes = array(
            $this->em->getClassMetadata(self::OWNING_ENTITY),
            $this->em->getClassMetadata(self::NON_OWNING_ENTITY),
        );

        try {
            $schemaTool->dropSchema($classes);
        } catch(\Exception $e) {
        }

        try {
            $schemaTool->createSchema($classes);
        } catch(\Exception $e) {
        }

        $this->listener = new EntityCollectionListener($this->registry, self::OWNING_ENTITY);
    }

    protected function tearDown()
    {
        $this->em = null;
        $this->registry = null;
    }

    public function testCreateEntityWithRelation()
    {
        $class = self::NON_OWNING_ENTITY;
        $nonOwningEntity = new $class();
        $nonOwningEntity->setName('foobar');

        $reflection = new \ReflectionClass($this->listener);

        $property = $reflection->getProperty('relation');
        $property->setAccessible(true);
        $property->setValue($this->listener, $nonOwningEntity);

        $method = $reflection->getMethod('createEntity');
        $method->setAccessible(true);

        $data = array('name' => 'barfoo');
        $entity = $method->invoke($this->listener, $data);

        $this->assertInstanceOf(self::OWNING_ENTITY, $entity);
        $this->assertEquals('barfoo', $entity->getName());
        $this->assertSame($nonOwningEntity, $entity->getNonOwningEntity());
    }

    public function testCreateEntityWithoutRelation()
    {
        $reflection = new \ReflectionClass($this->listener);

        $method = $reflection->getMethod('createEntity');
        $method->setAccessible(true);

        $data = array('name' => 'barfoo');
        $entity = $method->invoke($this->listener, $data);

        $this->assertInstanceOf(self::OWNING_ENTITY, $entity);
        $this->assertEquals('barfoo', $entity->getName());
    }

    public function testRemoveEntity()
    {
        $class = self::NON_OWNING_ENTITY;
        $entity = new $class();
        $entity->setName('foobar');

        $this->em->persist($entity);
        $this->em->flush();

        $reflection = new \ReflectionClass($this->listener);
        $method = $reflection->getMethod('removeEntity');
        $method->setAccessible(true);
        $method->invoke($this->listener, $entity);

        $this->assertTrue($this->em->getUnitOfWork()->isScheduledForDelete($entity));
    }

    public function testProcessArrayCollection()
    {
        $collection = new ArrayCollection();
        $collection[] = array('name' => 'foobar');
        $collection[] = array('name' => 'barfoo');

        $reflection = new \ReflectionClass($this->listener);
        $method = $reflection->getMethod('processArrayCollection');
        $method->setAccessible(true);
        $return = $method->invoke($this->listener, $collection);

        $this->assertInstanceOf(self::OWNING_ENTITY, $collection[0]);
        $this->assertInstanceOf(self::OWNING_ENTITY, $collection[1]);
        $this->assertEquals(2, count($collection));
    }

    public function testProcessPersistentCollection()
    {
        $noe = self::NON_OWNING_ENTITY;
        $noe = new $noe();
        $noe->setName('related');

        $oe = self::OWNING_ENTITY;
        $oe = new $oe();
        $oe->setNonOwningEntity($noe);
        $oe->setName('foobar');

        $this->em->persist($noe);
        $this->em->persist($oe);
        $this->em->flush();

        $array = new ArrayCollection();
        $array[] = $oe;

        $collection = new PersistentCollection($this->em, self::OWNING_ENTITY, $array);
        $collection->takeSnapshot();

        $collection[] = array('name' => 'barfoo');
        $collection->removeElement($oe);

        $reflection = new \ReflectionClass($this->listener);
        $method = $reflection->getMethod('processPersistentCollection');
        $method->setAccessible(true);
        $return = $method->invoke($this->listener, $collection);

        $this->assertEquals(1, count($collection));
        $this->assertTrue($this->em->getUnitOfWork()->isScheduledForDelete($oe));
        $this->assertInstanceOf(self::OWNING_ENTITY, $collection[2]);
        $this->assertEquals('barfoo', $collection[2]->getName());
    }

    public function testOnBindNormDataWithRelation()
    {
        $dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $noe = self::NON_OWNING_ENTITY;
        $noe = new $noe();
        $noe->setName('related');

        $oe = self::OWNING_ENTITY;
        $oe = new $oe();
        $oe->setName('foobar');
        $oe->setNonOwningEntity($noe);

        $oe2 = self::OWNING_ENTITY;
        $oe2 = new $oe2();
        $oe2->setName('barfoo');
        $oe2->setNonOwningEntity($noe);

        $noe->addOwningEntity($oe);
        $noe->addOwningEntity($oe2);

        $parentForm = new Form('parent', $dispatcher);
        $parentForm->setData($noe);
        $childForm = new Form('foobar', $dispatcher);
        $childForm->setParent($parentForm);

        $event = new FilterDataEvent($childForm, array());
        $this->listener->onBindNormData($event);

        $reflection = new \ReflectionClass($this->listener);
        $property = $reflection->getProperty('relation');
        $property->setAccessible(true);
        $this->assertSame($noe, $property->getValue($this->listener));
    }

    public function testOnBindNormDataWithoutRelation()
    {
        $dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $parentForm = new Form('parent', $dispatcher);
        $childForm = new Form('foobar', $dispatcher);
        $childForm->setParent($parentForm);

        $event = new FilterDataEvent($childForm, array());
        $this->listener->onBindNormData($event);

        $reflection = new \ReflectionClass($this->listener);
        $property = $reflection->getProperty('relation');
        $property->setAccessible(true);
        $this->assertNull($property->getValue($this->listener));
    }

    protected function createRegistryMock($name, $em)
    {
        $registry = $this->getMock('Symfony\Bridge\Doctrine\RegistryInterface');

        $registry->expects($this->any())
                 ->method('getEntityManagerForObject')
                 ->will($this->returnValue($em));

        return $registry;
    }
}

