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
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleAssociationToIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdNoToStringEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringCastableIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdEntity;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Tests\Extension\Core\Type\BaseTypeTest;
use Symfony\Component\Form\Tests\Extension\Core\Type\FormTypeTest;

class EntityTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Bridge\Doctrine\Form\Type\EntityType';

    const ITEM_GROUP_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\GroupableEntity';
    const SINGLE_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity';
    const SINGLE_IDENT_NO_TO_STRING_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdNoToStringEntity';
    const SINGLE_STRING_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdEntity';
    const SINGLE_ASSOC_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleAssociationToIntIdEntity';
    const SINGLE_STRING_CASTABLE_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringCastableIdEntity';
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

    protected static $supportedFeatureSetVersion = 304;

    protected function setUp()
    {
        $this->em = DoctrineTestHelper::createTestEntityManager();
        $this->emRegistry = $this->createRegistryMock('default', $this->em);

        parent::setUp();

        $schemaTool = new SchemaTool($this->em);
        $classes = [
            $this->em->getClassMetadata(self::ITEM_GROUP_CLASS),
            $this->em->getClassMetadata(self::SINGLE_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_IDENT_NO_TO_STRING_CLASS),
            $this->em->getClassMetadata(self::SINGLE_STRING_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_ASSOC_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_STRING_CASTABLE_IDENT_CLASS),
            $this->em->getClassMetadata(self::COMPOSITE_IDENT_CLASS),
            $this->em->getClassMetadata(self::COMPOSITE_STRING_IDENT_CLASS),
        ];

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
        return array_merge(parent::getExtensions(), [
            new DoctrineOrmExtension($this->emRegistry),
        ]);
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
        $this->factory->createNamed('name', static::TESTED_TYPE);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\RuntimeException
     */
    public function testInvalidClassOption()
    {
        $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'class' => 'foo',
        ]);
    }

    public function testSetDataToUninitializedEntityWithNonRequired()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
            'choice_label' => 'name',
        ]);

        $this->assertEquals([1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')], $field->createView()->vars['choices']);
    }

    public function testSetDataToUninitializedEntityWithNonRequiredToString()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $view = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
        ])
            ->createView();

        $this->assertEquals([1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')], $view->vars['choices']);
    }

    public function testSetDataToUninitializedEntityWithNonRequiredQueryBuilder()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);
        $qb = $this->em->createQueryBuilder()->select('e')->from(self::SINGLE_IDENT_CLASS, 'e');

        $view = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
            'choice_label' => 'name',
            'query_builder' => $qb,
        ])
            ->createView();

        $this->assertEquals([1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')], $view->vars['choices']);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testConfigureQueryBuilderWithNonQueryBuilderAndNonClosure()
    {
        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => new \stdClass(),
        ]);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConfigureQueryBuilderWithClosureReturningNonQueryBuilder()
    {
        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function () {
                return new \stdClass();
            },
        ]);

        $field->submit('2');
    }

    public function testConfigureQueryBuilderWithClosureReturningNullUseDefault()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function () {
                return;
            },
        ]);

        $this->assertEquals([1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')], $field->createView()->vars['choices']);
    }

    public function testSetDataSingleNull()
    {
        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ]);
        $field->setData(null);

        $this->assertNull($field->getData());
        $this->assertSame('', $field->getViewData());
    }

    public function testSetDataMultipleExpandedNull()
    {
        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ]);
        $field->setData(null);

        $this->assertNull($field->getData());
        $this->assertSame([], $field->getViewData());
    }

    public function testSetDataMultipleNonExpandedNull()
    {
        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ]);
        $field->setData(null);

        $this->assertNull($field->getData());
        $this->assertSame([], $field->getViewData());
    }

    public function testSubmitSingleNonExpandedSingleIdentifier()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

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

        $this->persist([$innerEntity1, $innerEntity2, $entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_ASSOC_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $field->submit('2');

        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity2, $field->getData());
        $this->assertSame('2', $field->getViewData());
    }

    public function testSubmitSingleNonExpandedCompositeIdentifier()
    {
        $entity1 = new CompositeIntIdEntity(10, 20, 'Foo');
        $entity2 = new CompositeIntIdEntity(30, 40, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

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

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $field->submit(['1', '3']);

        $expected = new ArrayCollection([$entity1, $entity3]);

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertSame(['1', '3'], $field->getViewData());
    }

    public function testSubmitMultipleNonExpandedSingleAssocIdentifier()
    {
        $innerEntity1 = new SingleIntIdNoToStringEntity(1, 'InFoo');
        $innerEntity2 = new SingleIntIdNoToStringEntity(2, 'InBar');
        $innerEntity3 = new SingleIntIdNoToStringEntity(3, 'InBaz');

        $entity1 = new SingleAssociationToIntIdEntity($innerEntity1, 'Foo');
        $entity2 = new SingleAssociationToIntIdEntity($innerEntity2, 'Bar');
        $entity3 = new SingleAssociationToIntIdEntity($innerEntity3, 'Baz');

        $this->persist([$innerEntity1, $innerEntity2, $innerEntity3, $entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_ASSOC_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $field->submit(['1', '3']);

        $expected = new ArrayCollection([$entity1, $entity3]);

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertSame(['1', '3'], $field->getViewData());
    }

    public function testSubmitMultipleNonExpandedSingleIdentifierForExistingData()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $existing = new ArrayCollection([0 => $entity2]);

        $field->setData($existing);
        $field->submit(['1', '3']);

        // entry with index 0 ($entity2) was replaced
        $expected = new ArrayCollection([0 => $entity1, 1 => $entity3]);

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        // same object still, useful if it is a PersistentCollection
        $this->assertSame($existing, $field->getData());
        $this->assertSame(['1', '3'], $field->getViewData());
    }

    public function testSubmitMultipleNonExpandedCompositeIdentifier()
    {
        $entity1 = new CompositeIntIdEntity(10, 20, 'Foo');
        $entity2 = new CompositeIntIdEntity(30, 40, 'Bar');
        $entity3 = new CompositeIntIdEntity(50, 60, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        // because of the composite key collection keys are used
        $field->submit(['0', '2']);

        $expected = new ArrayCollection([$entity1, $entity3]);

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertSame(['0', '2'], $field->getViewData());
    }

    public function testSubmitMultipleNonExpandedCompositeIdentifierExistingData()
    {
        $entity1 = new CompositeIntIdEntity(10, 20, 'Foo');
        $entity2 = new CompositeIntIdEntity(30, 40, 'Bar');
        $entity3 = new CompositeIntIdEntity(50, 60, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $existing = new ArrayCollection([0 => $entity2]);

        $field->setData($existing);
        $field->submit(['0', '2']);

        // entry with index 0 ($entity2) was replaced
        $expected = new ArrayCollection([0 => $entity1, 1 => $entity3]);

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        // same object still, useful if it is a PersistentCollection
        $this->assertSame($existing, $field->getData());
        $this->assertSame(['0', '2'], $field->getViewData());
    }

    public function testSubmitSingleExpanded()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

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

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $field->submit(['1', '3']);

        $expected = new ArrayCollection([$entity1, $entity3]);

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

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $field->submit(['-1']);

        $expected = new ArrayCollection([$entity1]);

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertTrue($field['_1']->getData());
        $this->assertFalse($field['2']->getData());
    }

    public function testSubmitSingleNonExpandedStringCastableIdentifier()
    {
        $entity1 = new SingleStringCastableIdEntity(1, 'Foo');
        $entity2 = new SingleStringCastableIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_STRING_CASTABLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $field->submit('2');

        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity2, $field->getData());
        $this->assertSame('2', $field->getViewData());
    }

    public function testSubmitSingleStringCastableIdentifierExpanded()
    {
        $entity1 = new SingleStringCastableIdEntity(1, 'Foo');
        $entity2 = new SingleStringCastableIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_STRING_CASTABLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $field->submit('2');

        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity2, $field->getData());
        $this->assertFalse($field['0']->getData());
        $this->assertTrue($field['1']->getData());
        $this->assertNull($field['0']->getViewData());
        $this->assertSame('2', $field['1']->getViewData());
    }

    public function testSubmitMultipleNonExpandedStringCastableIdentifierForExistingData()
    {
        $entity1 = new SingleStringCastableIdEntity(1, 'Foo');
        $entity2 = new SingleStringCastableIdEntity(2, 'Bar');
        $entity3 = new SingleStringCastableIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_STRING_CASTABLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $existing = new ArrayCollection([0 => $entity2]);

        $field->setData($existing);
        $field->submit(['1', '3']);

        // entry with index 0 ($entity2) was replaced
        $expected = new ArrayCollection([0 => $entity1, 1 => $entity3]);

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        // same object still, useful if it is a PersistentCollection
        $this->assertSame($existing, $field->getData());
        $this->assertSame(['1', '3'], $field->getViewData());
    }

    public function testSubmitMultipleNonExpandedStringCastableIdentifier()
    {
        $entity1 = new SingleStringCastableIdEntity(1, 'Foo');
        $entity2 = new SingleStringCastableIdEntity(2, 'Bar');
        $entity3 = new SingleStringCastableIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_STRING_CASTABLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $field->submit(['1', '3']);

        $expected = new ArrayCollection([$entity1, $entity3]);

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertSame(['1', '3'], $field->getViewData());
    }

    public function testSubmitMultipleStringCastableIdentifierExpanded()
    {
        $entity1 = new SingleStringCastableIdEntity(1, 'Foo');
        $entity2 = new SingleStringCastableIdEntity(2, 'Bar');
        $entity3 = new SingleStringCastableIdEntity(3, 'Bar');

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'em' => 'default',
            'class' => self::SINGLE_STRING_CASTABLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $field->submit(['1', '3']);

        $expected = new ArrayCollection([$entity1, $entity3]);

        $this->assertTrue($field->isSynchronized());
        $this->assertEquals($expected, $field->getData());
        $this->assertTrue($field['0']->getData());
        $this->assertFalse($field['1']->getData());
        $this->assertTrue($field['2']->getData());
        $this->assertSame('1', $field['0']->getViewData());
        $this->assertNull($field['1']->getViewData());
        $this->assertSame('3', $field['2']->getViewData());
    }

    public function testOverrideChoices()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            // not all persisted entities should be displayed
            'choices' => [$entity1, $entity2],
            'choice_label' => 'name',
        ]);

        $field->submit('2');

        $this->assertEquals([1 => new ChoiceView($entity1, '1', 'Foo'), 2 => new ChoiceView($entity2, '2', 'Bar')], $field->createView()->vars['choices']);
        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity2, $field->getData());
        $this->assertSame('2', $field->getViewData());
    }

    public function testOverrideChoicesValues()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
            'choice_value' => 'name',
        ]);

        $field->submit('Bar');

        $this->assertEquals(['Foo' => new ChoiceView($entity1, 'Foo', 'Foo'), 'Bar' => new ChoiceView($entity2, 'Bar', 'Bar')], $field->createView()->vars['choices']);
        $this->assertTrue($field->isSynchronized(), 'Field should be synchronized.');
        $this->assertSame($entity2, $field->getData(), 'Entity should be loaded by custom value.');
        $this->assertSame('Bar', $field->getViewData());
    }

    public function testOverrideChoicesValuesWithCallable()
    {
        $entity1 = new GroupableEntity(1, 'Foo', 'BazGroup');
        $entity2 = new GroupableEntity(2, 'Bar', 'BooGroup');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::ITEM_GROUP_CLASS,
            'choice_label' => 'name',
            'choice_value' => function (GroupableEntity $entity = null) {
                if (null === $entity) {
                    return '';
                }

                return $entity->groupName.'/'.$entity->name;
            },
        ]);

        $field->submit('BooGroup/Bar');

        $this->assertEquals([
            'BazGroup/Foo' => new ChoiceView($entity1, 'BazGroup/Foo', 'Foo'),
            'BooGroup/Bar' => new ChoiceView($entity2, 'BooGroup/Bar', 'Bar'),
            ], $field->createView()->vars['choices']);
        $this->assertTrue($field->isSynchronized(), 'Field should be synchronized.');
        $this->assertSame($entity2, $field->getData(), 'Entity should be loaded by custom value.');
        $this->assertSame('BooGroup/Bar', $field->getViewData());
    }

    public function testChoicesForValuesOptimization()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        $this->persist([$entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $this->em->clear();

        $field->submit(1);

        $unitOfWorkIdentityMap = $this->em->getUnitOfWork()->getIdentityMap();
        $managedEntitiesNames = array_map('strval', $unitOfWorkIdentityMap['Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity']);

        $this->assertContains((string) $entity1, $managedEntitiesNames);
        $this->assertNotContains((string) $entity2, $managedEntitiesNames);
    }

    public function testGroupByChoices()
    {
        $item1 = new GroupableEntity(1, 'Foo', 'Group1');
        $item2 = new GroupableEntity(2, 'Bar', 'Group1');
        $item3 = new GroupableEntity(3, 'Baz', 'Group2');
        $item4 = new GroupableEntity(4, 'Boo!', null);

        $this->persist([$item1, $item2, $item3, $item4]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::ITEM_GROUP_CLASS,
            'choices' => [$item1, $item2, $item3, $item4],
            'choice_label' => 'name',
            'group_by' => 'groupName',
        ]);

        $field->submit('2');

        $this->assertSame('2', $field->getViewData());
        $this->assertEquals([
            'Group1' => new ChoiceGroupView('Group1', [
                1 => new ChoiceView($item1, '1', 'Foo'),
                2 => new ChoiceView($item2, '2', 'Bar'),
            ]),
            'Group2' => new ChoiceGroupView('Group2', [
                3 => new ChoiceView($item3, '3', 'Baz'),
            ]),
            4 => new ChoiceView($item4, '4', 'Boo!'),
        ], $field->createView()->vars['choices']);
    }

    public function testPreferredChoices()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'preferred_choices' => [$entity3, $entity2],
            'choice_label' => 'name',
        ]);

        $this->assertEquals([3 => new ChoiceView($entity3, '3', 'Baz'), 2 => new ChoiceView($entity2, '2', 'Bar')], $field->createView()->vars['preferred_choices']);
        $this->assertEquals([1 => new ChoiceView($entity1, '1', 'Foo')], $field->createView()->vars['choices']);
    }

    public function testOverrideChoicesWithPreferredChoices()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choices' => [$entity2, $entity3],
            'preferred_choices' => [$entity3],
            'choice_label' => 'name',
        ]);

        $this->assertEquals([3 => new ChoiceView($entity3, '3', 'Baz')], $field->createView()->vars['preferred_choices']);
        $this->assertEquals([2 => new ChoiceView($entity2, '2', 'Bar')], $field->createView()->vars['choices']);
    }

    public function testDisallowChoicesThatAreNotIncludedChoicesSingleIdentifier()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'choices' => [$entity1, $entity2],
            'choice_label' => 'name',
        ]);

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

        $this->persist([$innerEntity1, $innerEntity2, $entity1, $entity2]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_ASSOC_IDENT_CLASS,
            'choices' => [$entity1, $entity2],
            'choice_label' => 'name',
        ]);

        $field->submit('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedChoicesCompositeIdentifier()
    {
        $entity1 = new CompositeIntIdEntity(10, 20, 'Foo');
        $entity2 = new CompositeIntIdEntity(30, 40, 'Bar');
        $entity3 = new CompositeIntIdEntity(50, 60, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'choices' => [$entity1, $entity2],
            'choice_label' => 'name',
        ]);

        $field->submit('2');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedQueryBuilderSingleIdentifier()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $repository = $this->em->getRepository(self::SINGLE_IDENT_CLASS);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => $repository->createQueryBuilder('e')
                ->where('e.id IN (1, 2)'),
            'choice_label' => 'name',
        ]);

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

        $this->persist([$innerEntity1, $innerEntity2, $innerEntity3, $entity1, $entity2, $entity3]);

        $repository = $this->em->getRepository(self::SINGLE_ASSOC_IDENT_CLASS);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_ASSOC_IDENT_CLASS,
            'query_builder' => $repository->createQueryBuilder('e')
                ->where('e.entity IN (1, 2)'),
            'choice_label' => 'name',
        ]);

        $field->submit('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedQueryBuilderAsClosureSingleIdentifier()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function (EntityRepository $repository) {
                return $repository->createQueryBuilder('e')
                    ->where('e.id IN (1, 2)');
            },
            'choice_label' => 'name',
        ]);

        $field->submit('3');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testDisallowChoicesThatAreNotIncludedQueryBuilderAsClosureCompositeIdentifier()
    {
        $entity1 = new CompositeIntIdEntity(10, 20, 'Foo');
        $entity2 = new CompositeIntIdEntity(30, 40, 'Bar');
        $entity3 = new CompositeIntIdEntity(50, 60, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::COMPOSITE_IDENT_CLASS,
            'query_builder' => function (EntityRepository $repository) {
                return $repository->createQueryBuilder('e')
                    ->where('e.id1 IN (10, 50)');
            },
            'choice_label' => 'name',
        ]);

        $field->submit('2');

        $this->assertFalse($field->isSynchronized());
        $this->assertNull($field->getData());
    }

    public function testSubmitSingleStringIdentifier()
    {
        $entity1 = new SingleStringIdEntity('foo', 'Foo');

        $this->persist([$entity1]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::SINGLE_STRING_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

        $field->submit('foo');

        $this->assertTrue($field->isSynchronized());
        $this->assertSame($entity1, $field->getData());
        $this->assertSame('foo', $field->getViewData());
    }

    public function testSubmitCompositeStringIdentifier()
    {
        $entity1 = new CompositeStringIdEntity('foo1', 'foo2', 'Foo');

        $this->persist([$entity1]);

        $field = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'em' => 'default',
            'class' => self::COMPOSITE_STRING_IDENT_CLASS,
            'choice_label' => 'name',
        ]);

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

        $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'class' => self::SINGLE_IDENT_CLASS,
            'required' => false,
            'choice_label' => 'name',
        ]);
    }

    public function testExplicitEm()
    {
        $this->emRegistry->expects($this->never())
            ->method('getManager');

        $this->emRegistry->expects($this->never())
            ->method('getManagerForClass');

        $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => $this->em,
            'class' => self::SINGLE_IDENT_CLASS,
            'choice_label' => 'name',
        ]);
    }

    public function testLoaderCaching()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $repo = $this->em->getRepository(self::SINGLE_IDENT_CLASS);

        $entityType = new EntityType($this->emRegistry);

        $entityTypeGuesser = new DoctrineOrmTypeGuesser($this->emRegistry);

        $factory = Forms::createFormFactoryBuilder()
            ->addType($entityType)
            ->addTypeGuesser($entityTypeGuesser)
            ->getFormFactory();

        $formBuilder = $factory->createNamedBuilder('form', FormTypeTest::TESTED_TYPE);

        $formBuilder->add('property1', static::TESTED_TYPE, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => $repo->createQueryBuilder('e')->where('e.id IN (1, 2)'),
        ]);

        $formBuilder->add('property2', static::TESTED_TYPE, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function (EntityRepository $repo) {
                return $repo->createQueryBuilder('e')->where('e.id IN (1, 2)');
            },
        ]);

        $formBuilder->add('property3', static::TESTED_TYPE, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function (EntityRepository $repo) {
                return $repo->createQueryBuilder('e')->where('e.id IN (1, 2)');
            },
        ]);

        $form = $formBuilder->getForm();

        $form->submit([
            'property1' => 1,
            'property2' => 1,
            'property3' => 2,
        ]);

        $choiceLoader1 = $form->get('property1')->getConfig()->getOption('choice_loader');
        $choiceLoader2 = $form->get('property2')->getConfig()->getOption('choice_loader');
        $choiceLoader3 = $form->get('property3')->getConfig()->getOption('choice_loader');

        $this->assertInstanceOf('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface', $choiceLoader1);
        $this->assertSame($choiceLoader1, $choiceLoader2);
        $this->assertSame($choiceLoader1, $choiceLoader3);
    }

    public function testLoaderCachingWithParameters()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');
        $entity3 = new SingleIntIdEntity(3, 'Baz');

        $this->persist([$entity1, $entity2, $entity3]);

        $repo = $this->em->getRepository(self::SINGLE_IDENT_CLASS);

        $entityType = new EntityType($this->emRegistry);

        $entityTypeGuesser = new DoctrineOrmTypeGuesser($this->emRegistry);

        $factory = Forms::createFormFactoryBuilder()
            ->addType($entityType)
            ->addTypeGuesser($entityTypeGuesser)
            ->getFormFactory();

        $formBuilder = $factory->createNamedBuilder('form', FormTypeTest::TESTED_TYPE);

        $formBuilder->add('property1', static::TESTED_TYPE, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => $repo->createQueryBuilder('e')->where('e.id = :id')->setParameter('id', 1),
        ]);

        $formBuilder->add('property2', static::TESTED_TYPE, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function (EntityRepository $repo) {
                return $repo->createQueryBuilder('e')->where('e.id = :id')->setParameter('id', 1);
            },
        ]);

        $formBuilder->add('property3', static::TESTED_TYPE, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'query_builder' => function (EntityRepository $repo) {
                return $repo->createQueryBuilder('e')->where('e.id = :id')->setParameter('id', 1);
            },
        ]);

        $form = $formBuilder->getForm();

        $form->submit([
            'property1' => 1,
            'property2' => 1,
            'property3' => 2,
        ]);

        $choiceLoader1 = $form->get('property1')->getConfig()->getOption('choice_loader');
        $choiceLoader2 = $form->get('property2')->getConfig()->getOption('choice_loader');
        $choiceLoader3 = $form->get('property3')->getConfig()->getOption('choice_loader');

        $this->assertInstanceOf('Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface', $choiceLoader1);
        $this->assertSame($choiceLoader1, $choiceLoader2);
        $this->assertSame($choiceLoader1, $choiceLoader3);
    }

    protected function createRegistryMock($name, $em)
    {
        $registry = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')->getMock();
        $registry->expects($this->any())
            ->method('getManager')
            ->with($this->equalTo($name))
            ->will($this->returnValue($em));

        return $registry;
    }

    public function testPassDisabledAsOption()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'disabled' => true,
            'class' => self::SINGLE_IDENT_CLASS,
        ]);

        $this->assertTrue($form->isDisabled());
    }

    public function testPassIdAndNameToView()
    {
        $view = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ])
            ->createView();

        $this->assertEquals('name', $view->vars['id']);
        $this->assertEquals('name', $view->vars['name']);
        $this->assertEquals('name', $view->vars['full_name']);
    }

    public function testStripLeadingUnderscoresAndDigitsFromId()
    {
        $view = $this->factory->createNamed('_09name', static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ])
            ->createView();

        $this->assertEquals('name', $view->vars['id']);
        $this->assertEquals('_09name', $view->vars['name']);
        $this->assertEquals('_09name', $view->vars['full_name']);
    }

    public function testPassIdAndNameToViewWithParent()
    {
        $view = $this->factory->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE)
            ->add('child', static::TESTED_TYPE, [
                'em' => 'default',
                'class' => self::SINGLE_IDENT_CLASS,
            ])
            ->getForm()
            ->createView();

        $this->assertEquals('parent_child', $view['child']->vars['id']);
        $this->assertEquals('child', $view['child']->vars['name']);
        $this->assertEquals('parent[child]', $view['child']->vars['full_name']);
    }

    public function testPassIdAndNameToViewWithGrandParent()
    {
        $builder = $this->factory->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE)
            ->add('child', FormTypeTest::TESTED_TYPE);
        $builder->get('child')->add('grand_child', static::TESTED_TYPE, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ]);
        $view = $builder->getForm()->createView();

        $this->assertEquals('parent_child_grand_child', $view['child']['grand_child']->vars['id']);
        $this->assertEquals('grand_child', $view['child']['grand_child']->vars['name']);
        $this->assertEquals('parent[child][grand_child]', $view['child']['grand_child']->vars['full_name']);
    }

    public function testPassTranslationDomainToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'translation_domain' => 'domain',
        ])
            ->createView();

        $this->assertSame('domain', $view->vars['translation_domain']);
    }

    public function testInheritTranslationDomainFromParent()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE, null, [
                'translation_domain' => 'domain',
            ])
            ->add('child', static::TESTED_TYPE, [
                'em' => 'default',
                'class' => self::SINGLE_IDENT_CLASS,
            ])
            ->getForm()
            ->createView();

        $this->assertEquals('domain', $view['child']->vars['translation_domain']);
    }

    public function testPreferOwnTranslationDomain()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE, null, [
                'translation_domain' => 'parent_domain',
            ])
            ->add('child', static::TESTED_TYPE, [
                'em' => 'default',
                'class' => self::SINGLE_IDENT_CLASS,
                'translation_domain' => 'domain',
            ])
            ->getForm()
            ->createView();

        $this->assertEquals('domain', $view['child']->vars['translation_domain']);
    }

    public function testDefaultTranslationDomain()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE)
            ->add('child', static::TESTED_TYPE, [
                'em' => 'default',
                'class' => self::SINGLE_IDENT_CLASS,
            ])
            ->getForm()
            ->createView();

        $this->assertNull($view['child']->vars['translation_domain']);
    }

    public function testPassLabelToView()
    {
        $view = $this->factory->createNamed('__test___field', static::TESTED_TYPE, null, [
            'label' => 'My label',
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ])
            ->createView();

        $this->assertSame('My label', $view->vars['label']);
    }

    public function testPassMultipartFalseToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ])
            ->createView();

        $this->assertFalse($view->vars['multipart']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
        ]);
        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData(), 'View data is always a string');
    }

    public function testSubmitNullExpanded()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'expanded' => true,
        ]);
        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData(), 'View data is always a string');
    }

    public function testSubmitNullMultiple()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'multiple' => true,
        ]);
        $form->submit(null);

        $collection = new ArrayCollection();

        $this->assertEquals($collection, $form->getData());
        $this->assertEquals($collection, $form->getNormData());
        $this->assertSame([], $form->getViewData(), 'View data is always an array');
    }

    public function testSubmitNullExpandedMultiple()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'expanded' => true,
            'multiple' => true,
        ]);
        $form->submit(null);

        $collection = new ArrayCollection();

        $this->assertEquals($collection, $form->getData());
        $this->assertEquals($collection, $form->getNormData());
        $this->assertSame([], $form->getViewData(), 'View data is always an array');
    }

    public function testSetDataEmptyArraySubmitNullMultiple()
    {
        $emptyArray = [];
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'multiple' => true,
        ]);
        $form->setData($emptyArray);
        $form->submit(null);
        $this->assertInternalType('array', $form->getData());
        $this->assertEquals([], $form->getData());
        $this->assertEquals([], $form->getNormData());
        $this->assertSame([], $form->getViewData(), 'View data is always an array');
    }

    public function testSetDataNonEmptyArraySubmitNullMultiple()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $this->persist([$entity1]);
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'multiple' => true,
        ]);
        $existing = [0 => $entity1];
        $form->setData($existing);
        $form->submit(null);
        $this->assertInternalType('array', $form->getData());
        $this->assertEquals([], $form->getData());
        $this->assertEquals([], $form->getNormData());
        $this->assertSame([], $form->getViewData(), 'View data is always an array');
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'empty', $expectedData = null)
    {
        $emptyData = '1';
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $this->persist([$entity1]);

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        $this->assertSame($emptyData, $form->getViewData());
        $this->assertSame($entity1, $form->getNormData());
        $this->assertSame($entity1, $form->getData());
    }

    public function testSubmitNullMultipleUsesDefaultEmptyData()
    {
        $emptyData = ['1'];
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $this->persist([$entity1]);

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'em' => 'default',
            'class' => self::SINGLE_IDENT_CLASS,
            'multiple' => true,
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        $collection = new ArrayCollection([$entity1]);

        $this->assertSame($emptyData, $form->getViewData());
        $this->assertEquals($collection, $form->getNormData());
        $this->assertEquals($collection, $form->getData());
    }
}
