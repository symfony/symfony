<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form\Type;

use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Tests\Extension\Core\Type\TypeTestCase;
use Symfony\Bridge\Doctrine\Tests\DoctrineOrmTestCase;
use Symfony\Bridge\Doctrine\Tests\Fixtures\ItemGroupEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIdentEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdentEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIdentEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeStringIdentEntity;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;

class EntityTypeTest extends TypeTestCase
{
    const ITEM_GROUP_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\ItemGroupEntity';
    const SINGLE_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIdentEntity';
    const SINGLE_STRING_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdentEntity';
    const COMPOSITE_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIdentEntity';
    const COMPOSITE_STRING_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeStringIdentEntity';

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $emRegistry;

    protected function setUp()
    {
        $this->em = DoctrineOrmTestCase::createTestEntityManager();
        $this->emRegistry = $this->createRegistryMock('default', $this->em);

        parent::setUp();

        $schemaTool = new SchemaTool($this->em);
        $classes = array(
            $this->em->getClassMetadata(self::ITEM_GROUP_CLASS),
            $this->em->getClassMetadata(self::SINGLE_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_STRING_IDENT_CLASS),
            $this->em->getClassMetadata(self::COMPOSITE_IDENT_CLASS),
            $this->em->getClassMetadata(self::COMPOSITE_STRING_IDENT_CLASS),
        );

        try {
            $schemaTool->dropSchema($classes);
        } catch (\Exception $e) {
        }

        try {
            $schemaTool->createSchema($classes);
        } catch (\Exception $e) {
        }
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->em = null;
        $this->emRegistry = null;
    }

    protected function getExtensions()
    {
        return array_merge(parent::getExtensions(), array(
            new DoctrineOrmExtension($this->emRegistry),
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

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testClassOptionIsRequired()
    {
        $this->factory->createNamed('name', 'entity');
    }

    public function testSetDataToUninitializedEntityWithNonRequired()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
            'property' => 'name'
        ));

        $this->assertEquals(array(1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')), $field->createView()->vars['choices']);
    }

    public function testSetDataToUninitializedEntityWithNonRequiredToString()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
        ));

        $this->assertEquals(array(1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')), $field->createView()->vars['choices']);
    }

    public function testSetDataToUninitializedEntityWithNonRequiredQueryBuilder()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));
        $qb = $this->em->createQueryBuilder()->select('e')->from(self::SINGLE_IDENT_CLASS, 'e');

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
            'property' => 'name',
            'query_builder' => $qb
        ));

        $this->assertEquals(array(1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')), $field->createView()->vars['choices']);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConfigureQueryBuilderWithNonQueryBuilderAndNonClosure()
    {
        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => new \stdClass(),
        ));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConfigureQueryBuilderWithClosureReturningNonQueryBuilder()
    {
        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function () {
                return new \stdClass();
            },
        ));

        $field->submit('2');
    }

    public function testSetDataSingleNull()
    {
        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->setData(null);

        $this->assertNull($field->getData());
        $this->assertSame('', $field->getViewData());
    }

    public function testSetDataMultipleExpandedNull()
    {
        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => true,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->setData(null);

        $this->assertNull($field->getData());
        $this->assertSame(array(), $field->getViewData());
    }

    public function testSetDataMultipleNonExpandedNull()
    {
        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->setData(null);

        $this->assertNull($field->getData());
        $this->assertSame(array(), $field->getViewData());
    }

    public function testSubmitSingleExpandedNull()
    {
        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => false,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->submit(null);

        $this->assertNull($field->getData());
        $this->assertSame(array(), $field->getViewData());
    }

    public function testSubmitSingleNonExpandedNull()
    {
        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->submit(null);

        $this->assertNull($field->getData());
        $this->assertSame('', $field->getViewData());
    }

    public function testSubmitMultipleNull()
    {
        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->submit(null);

        $this->assertEquals(new ArrayCollection(), $field->getData());
        $this->assertSame(array(), $field->getViewData());
    }

    public function testSubmitSingleNonExpandedSingleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->submit('2');

        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity2, $field->getData());
        $this->assertSame('2', $field->getViewData());
    }

    public function testSubmitSingleNonExpandedCompositeIdentifier()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'property' => 'name',
        ));

        // the collection key is used here
        $field->submit('1');

        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity2, $field->getData());
        $this->assertSame('1', $field->getViewData());
    }

    public function testSubmitMultipleNonExpandedSingleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->submit(array('1', '3'));

        $expected = new ArrayCollection(array($entity1, $entity3));

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertSame(array('1', '3'), $field->getViewData());
    }

    public function testSubmitMultipleNonExpandedSingleIdentifierForExistingData()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $existing = new ArrayCollection(array(0 => $entity2));

        $field->setData($existing);
        $field->submit(array('1', '3'));

        // entry with index 0 ($entity2) was replaced
        $expected = new ArrayCollection(array(0 => $entity1, 1 => $entity3));

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        // same object still, useful if it is a PersistentCollection
        $this->assertSame($existing, $field->getData());
        $this->assertSame(array('1', '3'), $field->getViewData());
    }

    public function testSubmitMultipleNonExpandedCompositeIdentifier()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');
        $entity3 = new CompositeIdentEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'property' => 'name',
        ));

        // because of the composite key collection keys are used
        $field->submit(array('0', '2'));

        $expected = new ArrayCollection(array($entity1, $entity3));

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertSame(array('0', '2'), $field->getViewData());
    }

    public function testSubmitMultipleNonExpandedCompositeIdentifierExistingData()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');
        $entity3 = new CompositeIdentEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'property' => 'name',
        ));

        $existing = new ArrayCollection(array(0 => $entity2));

        $field->setData($existing);
        $field->submit(array('0', '2'));

        // entry with index 0 ($entity2) was replaced
        $expected = new ArrayCollection(array(0 => $entity1, 1 => $entity3));

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        // same object still, useful if it is a PersistentCollection
        $this->assertSame($existing, $field->getData());
        $this->assertSame(array('0', '2'), $field->getViewData());
    }

    public function testSubmitSingleExpanded()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => false,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->submit('2');

        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity2, $field->getData());
        $this->assertFalse($field['1']->getData());
        $this->assertTrue($field['2']->getData());
        $this->assertNull($field['1']->getViewData());
        $this->assertSame('2', $field['2']->getViewData());
    }

    public function testSubmitMultipleExpanded()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Bar');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => true,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->submit(array('1', '3'));

        $expected = new ArrayCollection(array($entity1, $entity3));

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertTrue($field['1']->getData());
        $this->assertFalse($field['2']->getData());
        $this->assertTrue($field['3']->getData());
        $this->assertSame('1', $field['1']->getViewData());
        $this->assertNull($field['2']->getViewData());
        $this->assertSame('3', $field['3']->getViewData());
    }

    public function testOverrideChoices()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            // not all persisted entities should be displayed
            'choices' => array($entity1, $entity2),
            'property' => 'name',
        ));

        $field->submit('2');

        $this->assertEquals(array(1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')), $field->createView()->vars['choices']);
        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity2, $field->getData());
        $this->assertSame('2', $field->getViewData());
    }

    public function testGroupByChoices()
    {
        $item1 = new ItemGroupEntity(1, 'Foo', 'Group1');
        $item2 = new ItemGroupEntity(2, 'Bar', 'Group1');
        $item3 = new ItemGroupEntity(3, 'Baz', 'Group2');
        $item4 = new ItemGroupEntity(4, 'Boo!', null);

        $this->persist(array($item1, $item2, $item3, $item4));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::ITEM_GROUP_CLASS,
            'choices' => array($item1, $item2, $item3, $item4),
            'property' => 'name',
            'group_by' => 'groupName',
        ));

        $field->submit('2');

        $this->assertSame('2', $field->getViewData());
        $this->assertEquals(array(
            'Group1' => array(1 => new ChoiceView($item1, '1', 'Foo'), 2 => new ChoiceView($item2, '2', 'Bar')),
            'Group2' => array(3 => new ChoiceView($item3, '3', 'Baz')),
            '4' => new ChoiceView($item4, '4', 'Boo!')
        ), $field->createView()->vars['choices']);
    }

    public function testPreferredChoices()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'preferred_choices' => array($entity3, $entity2),
            'property' => 'name',
        ));

        $this->assertEquals(array(3 => new ChoiceView($entity3, '3', 'Baz'), 2 => new ChoiceView($entity2, '2', 'Bar')), $field->createView()->vars['preferred_choices']);
        $this->assertEquals(array(1 => new ChoiceView($entity1, '1', 'Foo')), $field->createView()->vars['choices']);
    }

    public function testOverrideChoicesWithPreferredChoices()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choices' => array($entity2, $entity3),
            'preferred_choices' => array($entity3),
            'property' => 'name',
        ));

        $this->assertEquals(array(3 => new ChoiceView($entity3, '3', 'Baz')), $field->createView()->vars['preferred_choices']);
        $this->assertEquals(array(2 => new ChoiceView($entity2, '2', 'Bar')), $field->createView()->vars['choices']);
    }

    public function testDisallowChoicesThatAreNotIncludedChoicesSingleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choices' => array($entity1, $entity2),
            'property' => 'name',
        ));

        $field->submit('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedChoicesCompositeIdentifier()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');
        $entity3 = new CompositeIdentEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'choices' => array($entity1, $entity2),
            'property' => 'name',
        ));

        $field->submit('2');

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

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => $repository->createQueryBuilder('e')
                ->where('e.id IN (1, 2)'),
            'property' => 'name',
        ));

        $field->submit('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedQueryBuilderAsClosureSingleIdentifier()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');
        $entity3 = new SingleIdentEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function ($repository) {
                return $repository->createQueryBuilder('e')
                        ->where('e.id IN (1, 2)');
            },
            'property' => 'name',
        ));

        $field->submit('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedQueryBuilderAsClosureCompositeIdentifier()
    {
        $entity1 = new CompositeIdentEntity(10, 20, 'Foo');
        $entity2 = new CompositeIdentEntity(30, 40, 'Bar');
        $entity3 = new CompositeIdentEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'query_builder' => function ($repository) {
                return $repository->createQueryBuilder('e')
                        ->where('e.id1 IN (10, 50)');
            },
            'property' => 'name',
        ));

        $field->submit('2');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testSubmitSingleStringIdentifier()
    {
        $entity1 = new SingleStringIdentEntity('foo', 'Foo');

        $this->persist(array($entity1));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_STRING_IDENT_CLASS,
            'property' => 'name',
        ));

        $field->submit('foo');

        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity1, $field->getData());
        $this->assertSame('foo', $field->getViewData());
    }

    public function testSubmitCompositeStringIdentifier()
    {
        $entity1 = new CompositeStringIdentEntity('foo1', 'foo2', 'Foo');

        $this->persist(array($entity1));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_STRING_IDENT_CLASS,
            'property' => 'name',
        ));

        // the collection key is used here
        $field->submit('0');

        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity1, $field->getData());
        $this->assertSame('0', $field->getViewData());
    }

    public function testGetManagerForClassIfNoEm()
    {
        $this->emRegistry->expects($this->never())
            ->method('getManager');

        $this->emRegistry->expects($this->once())
            ->method('getManagerForClass')
            ->with(self::SINGLE_IDENT_CLASS)
            ->will($this->returnValue($this->em));

        $this->factory->createNamed('name', 'entity', null, array(
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
            'property' => 'name'
        ));
    }

    protected function createRegistryMock($name, $em)
    {
        $registry = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');
        $registry->expects($this->any())
                 ->method('getManager')
                 ->with($this->equalTo($name))
                 ->will($this->returnValue($em));

        return $registry;
    }
}
