<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Doctrine\Form\Type;

require_once __DIR__.'/../../DoctrineOrmTestCase.php';
require_once __DIR__.'/../../Fixtures/SingleIdentEntity.php';
require_once __DIR__.'/../../Fixtures/SingleStringIdentEntity.php';
require_once __DIR__.'/../../Fixtures/CompositeIdentEntity.php';
require_once __DIR__.'/../../Fixtures/CompositeStringIdentEntity.php';

use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Tests\Component\Form\Extension\Core\Type\TypeTestCase;
use Symfony\Tests\Bridge\Doctrine\DoctrineOrmTestCase;
use Symfony\Tests\Bridge\Doctrine\Fixtures\SingleIdentEntity;
use Symfony\Tests\Bridge\Doctrine\Fixtures\SingleStringIdentEntity;
use Symfony\Tests\Bridge\Doctrine\Fixtures\CompositeIdentEntity;
use Symfony\Tests\Bridge\Doctrine\Fixtures\CompositeStringIdentEntity;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Common\Collections\ArrayCollection;

class EntityTypeTest extends TypeTestCase
{
    const SINGLE_IDENT_CLASS = 'Symfony\Tests\Bridge\Doctrine\Fixtures\SingleIdentEntity';
    const SINGLE_STRING_IDENT_CLASS = 'Symfony\Tests\Bridge\Doctrine\Fixtures\SingleStringIdentEntity';
    const COMPOSITE_IDENT_CLASS = 'Symfony\Tests\Bridge\Doctrine\Fixtures\CompositeIdentEntity';
    const COMPOSITE_STRING_IDENT_CLASS = 'Symfony\Tests\Bridge\Doctrine\Fixtures\CompositeStringIdentEntity';

    private $em;

    protected function setUp()
    {
        if (!class_exists('Doctrine\\Common\\Version')) {
            $this->markTestSkipped('Doctrine is not available.');
        }

        $this->em = DoctrineOrmTestCase::createTestEntityManager();

        parent::setUp();

        $schemaTool = new SchemaTool($this->em);
        $classes = array(
            $this->em->getClassMetadata(self::SINGLE_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_STRING_IDENT_CLASS),
            $this->em->getClassMetadata(self::COMPOSITE_IDENT_CLASS),
            $this->em->getClassMetadata(self::COMPOSITE_STRING_IDENT_CLASS),
        );

        try {
            $schemaTool->dropSchema($classes);
        } catch(\Exception $e) {
        }

        try {
            $schemaTool->createSchema($classes);
        } catch(\Exception $e) {
        }
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->em = null;
    }

    protected function getExtensions()
    {
        return array_merge(parent::getExtensions(), array(
            new DoctrineOrmExtension($this->createRegistryMock('default', $this->em)),
        ));
    }

    protected function persist(array $entities)
    {
        foreach ($entities as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
        // no clear, because entities managed by the choice field must
        // be managed!
    }

    public function testSetDataToUninitializedEntityWithNonRequired()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
            'property' => 'name'
        ));

        $this->assertEquals(array(1 => 'Foo', 2 => 'Bar'), $field->createView()->get('choices'));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConfigureQueryBuilderWithNonQueryBuilderAndNonClosure()
    {
        $field = $this->factory->createNamed('entity', 'name', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => new \stdClass(),
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConfigureQueryBuilderWithClosureReturningNonQueryBuilder()
    {
        $field = $this->factory->createNamed('entity', 'name', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function () {
                return new \stdClass();
            },
        ));

        $field->bind('2');
    }

    public function testSetDataSingleNull()
    {
        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->setData(null);

        $this->assertEquals(null, $field->getData());
        $this->assertEquals('', $field->getClientData());
    }

    public function testSetDataMultipleExpandedNull()
    {
        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => true,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->setData(null);

        $this->assertEquals(null, $field->getData());
        $this->assertEquals(array(), $field->getClientData());
    }

    public function testSetDataMultipleNonExpandedNull()
    {
        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->setData(null);

        $this->assertEquals(null, $field->getData());
        $this->assertEquals(array(), $field->getClientData());
    }

    public function testSubmitSingleExpandedNull()
    {
        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => false,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->bind(null);

        $this->assertEquals(null, $field->getData());
        $this->assertEquals(array(), $field->getClientData());
    }

    public function testSubmitSingleNonExpandedNull()
    {
        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->bind(null);

        $this->assertEquals(null, $field->getData());
        $this->assertEquals('', $field->getClientData());
    }

    public function testSubmitMultipleNull()
    {
        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->bind(null);

        $this->assertEquals(new ArrayCollection(), $field->getData());
        $this->assertEquals(array(), $field->getClientData());
    }

    public function testSubmitSingleNonExpandedSingleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->bind('2');

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($entity2, $field->getData());
        $this->assertEquals(2, $field->getClientData());
    }

    public function testSubmitSingleNonExpandedCompositeIdentifier()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'property' => 'name',
        ));

        // the collection key is used here
        $field->bind('1');

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($entity2, $field->getData());
        $this->assertEquals(1, $field->getClientData());
    }

    public function testSubmitMultipleNonExpandedSingleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->bind(array('1', '3'));

        $expected = new ArrayCollection(array($entity1, $entity3));

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertEquals(array(1, 3), $field->getClientData());
    }

    public function testSubmitMultipleNonExpandedSingleIdentifier_existingData()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $existing = new ArrayCollection(array($entity2));

        $field->setData($existing);
        $field->bind(array('1', '3'));

        // entry with index 0 was removed
        $expected = new ArrayCollection(array(1 => $entity1, 2 => $entity3));

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        // same object still, useful if it is a PersistentCollection
        $this->assertSame($existing, $field->getData());
        $this->assertEquals(array(1, 3), $field->getClientData());
    }

    public function testSubmitMultipleNonExpandedCompositeIdentifier()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');
        $entity3 = new CompositeIdentEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'property' => 'name',
        ));

        // because of the composite key collection keys are used
        $field->bind(array('0', '2'));

        $expected = new ArrayCollection(array($entity1, $entity3));

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertEquals(array(0, 2), $field->getClientData());
    }

    public function testSubmitMultipleNonExpandedCompositeIdentifier_existingData()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');
        $entity3 = new CompositeIdentEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'property' => 'name',
        ));

        $existing = new ArrayCollection(array(0 => $entity2));

        $field->setData($existing);
        $field->bind(array('0', '2'));

        // entry with index 0 was removed
        $expected = new ArrayCollection(array(1 => $entity1, 2 => $entity3));

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        // same object still, useful if it is a PersistentCollection
        $this->assertSame($existing, $field->getData());
        $this->assertEquals(array(0, 2), $field->getClientData());
    }

    public function testSubmitSingleExpanded()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => false,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->bind('2');

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($entity2, $field->getData());
        $this->assertSame(false, $field['1']->getData());
        $this->assertSame(true, $field['2']->getData());
        $this->assertSame('', $field['1']->getClientData());
        $this->assertSame('1', $field['2']->getClientData());
    }

    public function testSubmitMultipleExpanded()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Bar');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => true,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->bind(array('1' => '1', '3' => '3'));

        $expected = new ArrayCollection(array($entity1, $entity3));

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertSame(true, $field['1']->getData());
        $this->assertSame(false, $field['2']->getData());
        $this->assertSame(true, $field['3']->getData());
        $this->assertSame('1', $field['1']->getClientData());
        $this->assertSame('', $field['2']->getClientData());
        $this->assertSame('1', $field['3']->getClientData());
    }

    public function testOverrideChoices()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            // not all persisted entities should be displayed
            'choices' => array($entity1, $entity2),
            'property' => 'name',
        ));

        $field->bind('2');

        $this->assertEquals(array(1 => 'Foo', 2 => 'Bar'), $field->createView()->get('choices'));
        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($entity2, $field->getData());
        $this->assertEquals(2, $field->getClientData());
    }

    public function testDisallowChoicesThatAreNotIncluded_choicesSingleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choices' => array($entity1, $entity2),
            'property' => 'name',
        ));

        $field->bind('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncluded_choicesCompositeIdentifier()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');
        $entity3 = new CompositeIdentEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'choices' => array($entity1, $entity2),
            'property' => 'name',
        ));

        $field->bind('2');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedQueryBuilderSingleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $repository = $this->em->getRepository(self::SINGLE_IDENT_CLASS);

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => $repository->createQueryBuilder('e')
                ->where('e.id IN (1, 2)'),
            'property' => 'name',
        ));

        $field->bind('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedQueryBuilderAsClosureSingleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function ($repository) {
                return $repository->createQueryBuilder('e')
                        ->where('e.id IN (1, 2)');
            },
            'property' => 'name',
        ));

        $field->bind('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedQueryBuilderAsClosureCompositeIdentifier()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');
        $entity3 = new CompositeIdentEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'query_builder' => function ($repository) {
                return $repository->createQueryBuilder('e')
                        ->where('e.id1 IN (10, 50)');
            },
            'property' => 'name',
        ));

        $field->bind('2');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testSubmitSingleStringIdentifier()
    {
        $entity1 = new SingleStringIdentEntity('foo', 'Foo');

        $this->persist(array($entity1));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_STRING_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->bind('foo');

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($entity1, $field->getData());
        $this->assertEquals('foo', $field->getClientData());
    }

    public function testSubmitCompositeStringIdentifier()
    {
        $entity1 = new CompositeStringIdentEntity('foo1', 'foo2', 'Foo');

        $this->persist(array($entity1));

        $field = $this->factory->createNamed('entity', 'name', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_STRING_IDENT_CLASS,
            'property' => 'name',
        ));

        // the collection key is used here
        $field->bind('0');

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($entity1, $field->getData());
        $this->assertEquals(0, $field->getClientData());
    }

    protected function createRegistryMock($name, $em)
    {
        $registry = $this->getMock('Symfony\Bridge\Doctrine\RegistryInterface');
        $registry->expects($this->any())
                 ->method('getEntityManager')
                 ->with($this->equalTo($name))
                 ->will($this->returnValue($em));

        return $registry;
    }
}
