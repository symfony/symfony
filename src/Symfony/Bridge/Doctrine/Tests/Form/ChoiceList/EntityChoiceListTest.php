<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form\ChoiceList;

use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;
use Symfony\Bridge\Doctrine\Tests\DoctrineOrmTestCase;
use Symfony\Bridge\Doctrine\Tests\Fixtures\ItemGroupEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIdentEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdentEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\NoToStringSingleIdentEntity;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;
use Doctrine\ORM\Tools\SchemaTool;

class EntityChoiceListTest extends DoctrineOrmTestCase
{
    const ITEM_GROUP_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\ItemGroupEntity';

    const SINGLE_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIdentEntity';

    const SINGLE_STRING_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdentEntity';

    const COMPOSITE_IDENT_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIdentEntity';

    private $em;

    protected function setUp()
    {
        parent::setUp();

        $this->em = $this->createTestEntityManager();

        $schemaTool = new SchemaTool($this->em);
        $classes = array(
            $this->em->getClassMetadata(self::ITEM_GROUP_CLASS),
            $this->em->getClassMetadata(self::SINGLE_IDENT_CLASS),
            $this->em->getClassMetadata(self::SINGLE_STRING_IDENT_CLASS),
            $this->em->getClassMetadata(self::COMPOSITE_IDENT_CLASS),
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
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\StringCastException
     * @expectedMessage   Entity "Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIdentEntity" passed to the choice field must have a "__toString()" method defined (or you can also override the "property" option).
     */
    public function testEntitiesMustHaveAToStringMethod()
    {
        $entity1 = new NoToStringSingleIdentEntity(1, 'Foo');
        $entity2 = new NoToStringSingleIdentEntity(2, 'Bar');

        // Persist for managed state
        $this->em->persist($entity1);
        $this->em->persist($entity2);

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_IDENT_CLASS,
            null,
            null,
            array(
                $entity1,
                $entity2,
            )
        );

        $choiceList->getValues();
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\RuntimeException
     */
    public function testChoicesMustBeManaged()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        // no persist here!

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_IDENT_CLASS,
            'name',
            null,
            array(
                $entity1,
                $entity2,
            )
        );

        // triggers loading -> exception
        $choiceList->getChoices();
    }

    public function testFlattenedChoicesAreManaged()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        // Persist for managed state
        $this->em->persist($entity1);
        $this->em->persist($entity2);

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_IDENT_CLASS,
            'name',
            null,
            array(
                $entity1,
                $entity2,
            )
        );

        $this->assertSame(array(1 => $entity1, 2 => $entity2), $choiceList->getChoices());
    }

    public function testEmptyChoicesAreManaged()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        // Persist for managed state
        $this->em->persist($entity1);
        $this->em->persist($entity2);

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_IDENT_CLASS,
            'name',
            null,
            array()
        );

        $this->assertSame(array(), $choiceList->getChoices());
    }

    public function testNestedChoicesAreManaged()
    {
        $entity1 = new SingleIdentEntity(1, 'Foo');
        $entity2 = new SingleIdentEntity(2, 'Bar');

        // Oh yeah, we're persisting with fire now!
        $this->em->persist($entity1);
        $this->em->persist($entity2);

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_IDENT_CLASS,
            'name',
            null,
            array(
                'group1' => array($entity1),
                'group2' => array($entity2),
            ),
            array()
        );

        $this->assertSame(array(1 => $entity1, 2 => $entity2), $choiceList->getChoices());
        $this->assertEquals(array(
            'group1' => array(1 => new ChoiceView($entity1, '1', 'Foo')),
            'group2' => array(2 => new ChoiceView($entity2, '2', 'Bar'))
        ), $choiceList->getRemainingViews());
    }

    public function testGroupBySupportsString()
    {
        $item1 = new ItemGroupEntity(1, 'Foo', 'Group1');
        $item2 = new ItemGroupEntity(2, 'Bar', 'Group1');
        $item3 = new ItemGroupEntity(3, 'Baz', 'Group2');
        $item4 = new ItemGroupEntity(4, 'Boo!', null);

        $this->em->persist($item1);
        $this->em->persist($item2);
        $this->em->persist($item3);
        $this->em->persist($item4);

        $choiceList = new EntityChoiceList(
            $this->em,
            self::ITEM_GROUP_CLASS,
            'name',
            null,
            array(
                $item1,
                $item2,
                $item3,
                $item4,
            ),
            array(),
            'groupName'
        );

        $this->assertEquals(array(1 => $item1, 2 => $item2, 3 => $item3, 4 => $item4), $choiceList->getChoices());
        $this->assertEquals(array(
            'Group1' => array(1 => new ChoiceView($item1, '1', 'Foo'), 2 => new ChoiceView($item2, '2', 'Bar')),
            'Group2' => array(3 => new ChoiceView($item3, '3', 'Baz')),
            4 => new ChoiceView($item4, '4', 'Boo!')
        ), $choiceList->getRemainingViews());
    }

    public function testGroupByInvalidPropertyPathReturnsFlatChoices()
    {
        $item1 = new ItemGroupEntity(1, 'Foo', 'Group1');
        $item2 = new ItemGroupEntity(2, 'Bar', 'Group1');

        $this->em->persist($item1);
        $this->em->persist($item2);

        $choiceList = new EntityChoiceList(
            $this->em,
            self::ITEM_GROUP_CLASS,
            'name',
            null,
            array(
                $item1,
                $item2,
            ),
            array(),
            'child.that.does.not.exist'
        );

        $this->assertEquals(array(
            1 => $item1,
            2 => $item2
        ), $choiceList->getChoices());
    }

    public function testPossibleToProvideShorthandEntityName()
    {
        $shorthandName = 'SymfonyTestsDoctrine:SingleIdentEntity';

        $item1 = new SingleIdentEntity(1, 'Foo');
        $item2 = new SingleIdentEntity(2, 'Bar');

        $this->em->persist($item1);
        $this->em->persist($item2);

        $choiceList = new EntityChoiceList(
            $this->em,
            $shorthandName,
            null,
            null,
            null,
            array(),
            null
        );

        $this->assertEquals(array(1, 2), $choiceList->getValuesForChoices(array($item1, $item2)));
        $this->assertEquals(array(1, 2), $choiceList->getIndicesForChoices(array($item1, $item2)));
    }

    // Ticket #3446
    public function testGetEmptyArrayChoicesForEmptyValues()
    {
        $qb = $this->em->createQueryBuilder()->select('s')->from(self::SINGLE_IDENT_CLASS, 's');
        $entityLoader = new ORMQueryBuilderLoader($qb);
        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_IDENT_CLASS,
            null,
            $entityLoader
        );

        $this->assertEquals(array(), $choiceList->getChoicesForValues(array()));
    }

    // https://github.com/symfony/symfony/issues/3635
    public function testSingleNonIntIdFallsBackToGeneration()
    {
        $entity1 = new SingleStringIdentEntity('Id 1', 'Foo');
        $entity2 = new SingleStringIdentEntity('Id 2', 'Bar');

        // Persist for managed state
        $this->em->persist($entity1);
        $this->em->persist($entity2);
        $this->em->flush();

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_STRING_IDENT_CLASS,
            'name'
        );

        $this->assertSame(array(0 => $entity1, 1 => $entity2), $choiceList->getChoices());
    }

    public function testMinusReplacedByUnderscoreInNegativeIntIds()
    {
        $entity1 = new SingleIdentEntity(-1, 'Foo');
        $entity2 = new SingleIdentEntity(1, 'Bar');

        // Persist for managed state
        $this->em->persist($entity1);
        $this->em->persist($entity2);
        $this->em->flush();

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_IDENT_CLASS,
            'name'
        );

        $this->assertSame(array('_1' => $entity1, 1 => $entity2), $choiceList->getChoices());
        $this->assertSame(array('_1', 1), $choiceList->getIndicesForChoices(array($entity1, $entity2)));
        $this->assertSame(array('_1', 1), $choiceList->getIndicesForValues(array('-1', '1')));
    }

    public function testMinusReplacedByUnderscoreIfNotLoaded()
    {
        $entity1 = new SingleIdentEntity(-1, 'Foo');
        $entity2 = new SingleIdentEntity(1, 'Bar');

        // Persist for managed state
        $this->em->persist($entity1);
        $this->em->persist($entity2);
        $this->em->flush();

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_IDENT_CLASS,
            'name'
        );

        // no getChoices()!

        $this->assertSame(array('_1', 1), $choiceList->getIndicesForChoices(array($entity1, $entity2)));
        $this->assertSame(array('_1', 1), $choiceList->getIndicesForValues(array('-1', '1')));
    }
}
