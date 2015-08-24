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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmTypeGuesser;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeStringIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\GroupableEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdEntity;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleAssociationToIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdNoToStringEntity;

class EntityTypeTest extends TypeTestCase
{
    const ITEM_GROUP_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\GroupableEntity';
    const SINGLE_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity';
    const SINGLE_IDENT_NO_TO_STRING_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdNoToStringEntity';
    const SINGLE_STRING_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdEntity';
    const SINGLE_ASSOC_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleAssociationToIntIdEntity';
    const COMPOSITE_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIntIdEntity';
    const COMPOSITE_STRING_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeStringIdEntity';

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ManagerRegistry
     */
    private $emRegistry;

    protected function setUp()
    {
        $this->em = DoctrineTestHelper::createTestEntityManager();
        $this->emRegistry = $this->createRegistryMock('default', $this->em);

        parent::setUp();

        $schemaTool = new SchemaTool($this->em);
        $classes = array(
            $this->em->getClassMetadata(self::ITEM_GROUP_CLASS),
            $this->em->getClassMetadata(self::SINGLE_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_IDENT_NO_TO_STRING_CLASS),
            $this->em->getClassMetadata(self::SINGLE_STRING_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_ASSOC_IDENT_CLASS),
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
     * @group legacy
     */
    public function testLegacyName()
    {
        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));

        $this->assertSame('entity', $field->getConfig()->getType()->getName());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testClassOptionIsRequired()
    {
        $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\RuntimeException
     */
    public function testInvalidClassOption()
    {
        $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'class' => 'foo',
        ));
    }

    public function testSetDataToUninitializedEntityWithNonRequired()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
            'choice_label' => 'name',
        ));

        $this->assertEquals(array(1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')), $field->createView()->vars['choices']);
    }

    public function testSetDataToUninitializedEntityWithNonRequiredToString()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
        ));

        $this->assertEquals(array(1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')), $field->createView()->vars['choices']);
    }

    public function testSetDataToUninitializedEntityWithNonRequiredQueryBuilder()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));
        $qb = $this->em->createQueryBuilder()->select('e')->from(self::SINGLE_IDENT_CLASS, 'e');

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
            'choice_label' => 'name',
            'query_builder' => $qb,
        ));

        $this->assertEquals(array(1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')), $field->createView()->vars['choices']);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testConfigureQueryBuilderWithNonQueryBuilderAndNonClosure()
    {
        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
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
        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function () {
                return new \stdClass();
            },
        ));

        $field->submit('2');
    }

    public function testConfigureQueryBuilderWithClosureReturningNull()
    {
        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function () {
                return;
            },
        ));

        $this->assertEquals(array(), $field->createView()->vars['choices']);
    }

    public function testSetDataSingleNull()
    {
        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
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
        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
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
        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
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
        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'multiple' => false,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ));
        $field->submit(null);

        $this->assertNull($field->getData());
        $this->assertNull($field->getViewData());
    }

    public function testSubmitSingleNonExpandedNull()
    {
        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
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
        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
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
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ));

        $field->submit('2');

        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity2, $field->getData());
        $this->assertSame('2', $field->getViewData());
    }

    public function testSubmitSingleNonExpandedSingleAssocIdentifier()
    {
        $innerEntity1 = new SingleIntIdNoToStringEntity(1, 'InFoo');
        $innerEntity2 = new SingleIntIdNoToStringEntity(2, 'InBar');

        $entity1 = new SingleAssociationToIntIdEntity($innerEntity1, 'Foo');
        $entity2 = new SingleAssociationToIntIdEntity($innerEntity2, 'Bar');

        $this->persist(array($innerEntity1, $innerEntity2, $entity1, $entity2));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_ASSOC_IDENT_CLASS,
            'choice_label' => 'name',
        ));

        $field->submit('2');

        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity2, $field->getData());
        $this->assertSame('2', $field->getViewData());
    }

    public function testSubmitSingleNonExpandedCompositeIdentifier()
    {
        $entity1 = new CompositeIntIdEntity(10, 20, 'Foo');
        $entity2 = new CompositeIntIdEntity(30, 40, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'choice_label' => 'name',
        ));

        // the collection key is used here
        $field->submit('1');

        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity2, $field->getData());
        $this->assertSame('1', $field->getViewData());
    }

    public function testSubmitMultipleNonExpandedSingleIdentifier()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ));

        $field->submit(array('1', '3'));

        $expected = new ArrayCollection(array($entity1, $entity3));

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertSame(array('1', '3'), $field->getViewData());
    }

    public function testSubmitMultipleNonExpandedSingleAssocIdentifier()
    {
        $innerEntity1 = new SingleIntIdNoToStringEntity(1, 'InFoo');
        $innerEntity2 = new SingleIntIdNoToStringEntity(2, 'InBar');
        $innerEntity3 = new SingleIntIdNoToStringEntity(3, 'InBaz');

        $entity1 = new SingleAssociationToIntIdEntity($innerEntity1, 'Foo');
        $entity2 = new SingleAssociationToIntIdEntity($innerEntity2, 'Bar');
        $entity3 = new SingleAssociationToIntIdEntity($innerEntity3, 'Baz');

        $this->persist(array($innerEntity1, $innerEntity2, $innerEntity3, $entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_ASSOC_IDENT_CLASS,
            'choice_label' => 'name',
        ));

        $field->submit(array('1', '3'));

        $expected = new ArrayCollection(array($entity1, $entity3));

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertSame(array('1', '3'), $field->getViewData());
    }

    public function testSubmitMultipleNonExpandedSingleIdentifierForExistingData()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
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
        $entity1 = new CompositeIntIdEntity(10, 20, 'Foo');
        $entity2 = new CompositeIntIdEntity(30, 40, 'Bar');
        $entity3 = new CompositeIntIdEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'choice_label' => 'name',
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
        $entity1 = new CompositeIntIdEntity(10, 20, 'Foo');
        $entity2 = new CompositeIntIdEntity(30, 40, 'Bar');
        $entity3 = new CompositeIntIdEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'choice_label' => 'name',
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
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'multiple' => false,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
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
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Bar');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'multiple' => true,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
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

    public function testSubmitMultipleExpandedWithNegativeIntegerId()
    {
        $entity1 = new SingleIntIdEntity(-1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'multiple' => true,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ));

        $field->submit(array('-1'));

        $expected = new ArrayCollection(array($entity1));

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertTrue($field['_1']->getData());
        $this->assertFalse($field['2']->getData());
    }

    public function testOverrideChoices()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            // not all persisted entities should be displayed
            'choices' => array($entity1, $entity2),
            'choice_label' => 'name',
        ));

        $field->submit('2');

        $this->assertEquals(array(1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')), $field->createView()->vars['choices']);
        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity2, $field->getData());
        $this->assertSame('2', $field->getViewData());
    }

    public function testGroupByChoices()
    {
        $item1 = new GroupableEntity(1, 'Foo', 'Group1');
        $item2 = new GroupableEntity(2, 'Bar', 'Group1');
        $item3 = new GroupableEntity(3, 'Baz', 'Group2');
        $item4 = new GroupableEntity(4, 'Boo!', null);

        $this->persist(array($item1, $item2, $item3, $item4));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::ITEM_GROUP_CLASS,
            'choices' => array($item1, $item2, $item3, $item4),
            'choice_label' => 'name',
            'group_by' => 'groupName',
        ));

        $field->submit('2');

        $this->assertSame('2', $field->getViewData());
        $this->assertEquals(array(
            'Group1' => new ChoiceGroupView('Group1', array(
                1 => new ChoiceView($item1, '1', 'Foo'),
                2 => new ChoiceView($item2, '2', 'Bar'),
            )),
            'Group2' => new ChoiceGroupView('Group2', array(
                3 => new ChoiceView($item3, '3', 'Baz'),
            )),
            4 => new ChoiceView($item4, '4', 'Boo!'),
        ), $field->createView()->vars['choices']);
    }

    public function testPreferredChoices()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'preferred_choices' => array($entity3, $entity2),
            'choice_label' => 'name',
        ));

        $this->assertEquals(array(3 => new ChoiceView($entity3, '3', 'Baz'), 2 => new ChoiceView($entity2, '2', 'Bar')), $field->createView()->vars['preferred_choices']);
        $this->assertEquals(array(1 => new ChoiceView($entity1, '1', 'Foo')), $field->createView()->vars['choices']);
    }

    public function testOverrideChoicesWithPreferredChoices()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choices' => array($entity2, $entity3),
            'preferred_choices' => array($entity3),
            'choice_label' => 'name',
        ));

        $this->assertEquals(array(3 => new ChoiceView($entity3, '3', 'Baz')), $field->createView()->vars['preferred_choices']);
        $this->assertEquals(array(2 => new ChoiceView($entity2, '2', 'Bar')), $field->createView()->vars['choices']);
    }

    public function testDisallowChoicesThatAreNotIncludedChoicesSingleIdentifier()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choices' => array($entity1, $entity2),
            'choice_label' => 'name',
        ));

        $field->submit('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedChoicesSingleAssocIdentifier()
    {
        $innerEntity1 = new SingleIntIdNoToStringEntity(1, 'InFoo');
        $innerEntity2 = new SingleIntIdNoToStringEntity(2, 'InBar');

        $entity1 = new SingleAssociationToIntIdEntity($innerEntity1, 'Foo');
        $entity2 = new SingleAssociationToIntIdEntity($innerEntity2, 'Bar');

        $this->persist(array($innerEntity1, $innerEntity2, $entity1, $entity2));

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_ASSOC_IDENT_CLASS,
            'choices' => array($entity1, $entity2),
            'choice_label' => 'name',
        ));

        $field->submit('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedChoicesCompositeIdentifier()
    {
        $entity1 = new CompositeIntIdEntity(10, 20, 'Foo');
        $entity2 = new CompositeIntIdEntity(30, 40, 'Bar');
        $entity3 = new CompositeIntIdEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'choices' => array($entity1, $entity2),
            'choice_label' => 'name',
        ));

        $field->submit('2');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedQueryBuilderSingleIdentifier()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $repository = $this->em->getRepository(self::SINGLE_IDENT_CLASS);

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => $repository->createQueryBuilder('e')
                ->where('e.id IN (1, 2)'),
            'choice_label' => 'name',
        ));

        $field->submit('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedQueryBuilderSingleAssocIdentifier()
    {
        $innerEntity1 = new SingleIntIdNoToStringEntity(1, 'InFoo');
        $innerEntity2 = new SingleIntIdNoToStringEntity(2, 'InBar');
        $innerEntity3 = new SingleIntIdNoToStringEntity(3, 'InBaz');

        $entity1 = new SingleAssociationToIntIdEntity($innerEntity1, 'Foo');
        $entity2 = new SingleAssociationToIntIdEntity($innerEntity2, 'Bar');
        $entity3 = new SingleAssociationToIntIdEntity($innerEntity3, 'Baz');

        $this->persist(array($innerEntity1, $innerEntity2, $innerEntity3, $entity1, $entity2, $entity3));

        $repository = $this->em->getRepository(self::SINGLE_ASSOC_IDENT_CLASS);

        $field = $this->factory->createNamed('name', 'entity', null, array(
            'em' => 'default',
            'class' => self::SINGLE_ASSOC_IDENT_CLASS,
            'query_builder' => $repository->createQueryBuilder('e')
                ->where('e.entity IN (1, 2)'),
            'choice_label' => 'name',
        ));

        $field->submit('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedQueryBuilderAsClosureSingleIdentifier()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function ($repository) {
                return $repository->createQueryBuilder('e')
                    ->where('e.id IN (1, 2)');
            },
            'choice_label' => 'name',
        ));

        $field->submit('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedQueryBuilderAsClosureCompositeIdentifier()
    {
        $entity1 = new CompositeIntIdEntity(10, 20, 'Foo');
        $entity2 = new CompositeIntIdEntity(30, 40, 'Bar');
        $entity3 = new CompositeIntIdEntity(50, 60, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'query_builder' => function ($repository) {
                return $repository->createQueryBuilder('e')
                    ->where('e.id1 IN (10, 50)');
            },
            'choice_label' => 'name',
        ));

        $field->submit('2');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testSubmitSingleStringIdentifier()
    {
        $entity1 = new SingleStringIdEntity('foo', 'Foo');

        $this->persist(array($entity1));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_STRING_IDENT_CLASS,
            'choice_label' => 'name',
        ));

        $field->submit('foo');

        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity1, $field->getData());
        $this->assertSame('foo', $field->getViewData());
    }

    public function testSubmitCompositeStringIdentifier()
    {
        $entity1 = new CompositeStringIdEntity('foo1', 'foo2', 'Foo');

        $this->persist(array($entity1));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_STRING_IDENT_CLASS,
            'choice_label' => 'name',
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

        $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
            'choice_label' => 'name',
        ));
    }

    public function testExplicitEm()
    {
        $this->emRegistry->expects($this->never())
            ->method('getManager');

        $this->emRegistry->expects($this->never())
            ->method('getManagerForClass');

        $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ));
    }

    public function testLoaderCaching()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist(array($entity1, $entity2, $entity3));

        $repo = $this->em->getRepository(self::SINGLE_IDENT_CLASS);

        $entityType = new EntityType(
            $this->emRegistry,
            PropertyAccess::createPropertyAccessor()
        );

        $entityTypeGuesser = new DoctrineOrmTypeGuesser($this->emRegistry);

        $factory = Forms::createFormFactoryBuilder()
            ->addType($entityType)
            ->addTypeGuesser($entityTypeGuesser)
            ->getFormFactory();

        $formBuilder = $factory->createNamedBuilder('form', 'Symfony\Component\Form\Extension\Core\Type\FormType');

        $formBuilder->add('property1', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => $repo->createQueryBuilder('e')->where('e.id IN (1, 2)'),
        ));

        $formBuilder->add('property2', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function (EntityRepository $repo) {
                return $repo->createQueryBuilder('e')->where('e.id IN (1, 2)');
            },
        ));

        $formBuilder->add('property3', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function (EntityRepository $repo) {
                return $repo->createQueryBuilder('e')->where('e.id IN (1, 2)');
            },
        ));

        $form = $formBuilder->getForm();

        $form->submit(array(
            'property1' => 1,
            'property2' => 1,
            'property3' => 2,
        ));

        $choiceList1 = $form->get('property1')->getConfig()->getOption('choice_list');
        $choiceList2 = $form->get('property2')->getConfig()->getOption('choice_list');
        $choiceList3 = $form->get('property3')->getConfig()->getOption('choice_list');

        $this->assertInstanceOf('Symfony\Component\Form\ChoiceList\ChoiceListInterface', $choiceList1);
        $this->assertSame($choiceList1, $choiceList2);
        $this->assertSame($choiceList1, $choiceList3);
    }

    public function testCacheChoiceLists()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');

        $this->persist(array($entity1));

        $field1 = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
            'choice_label' => 'name',
        ));

        $field2 = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
            'choice_label' => 'name',
        ));

        $this->assertInstanceOf('Symfony\Component\Form\ChoiceList\ChoiceListInterface', $field1->getConfig()->getOption('choice_list'));
        $this->assertSame($field1->getConfig()->getOption('choice_list'), $field2->getConfig()->getOption('choice_list'));
    }

    /**
     * @group legacy
     */
    public function testPropertyOption()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist(array($entity1, $entity2));

        $field = $this->factory->createNamed('name', 'Symfony\Bridge\Doctrine\Form\Type\EntityType', null, array(
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
            'property' => 'name',
        ));

        $this->assertEquals(array(1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')), $field->createView()->vars['choices']);
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
