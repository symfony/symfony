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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\GroupableEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdNoToStringEntity;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * @group legacy
 */
class GenericEntityChoiceListTest extends TestCase
{
    const SINGLE_INT_ID_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity';

    const SINGLE_STRING_ID_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdEntity';

    const COMPOSITE_ID_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIntIdEntity';

    const GROUPABLE_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\GroupableEntity';

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    protected function setUp()
    {
        $this->em = DoctrineTestHelper::createTestEntityManager();

        $schemaTool = new SchemaTool($this->em);
        $classes = array(
            $this->em->getClassMetadata(self::SINGLE_INT_ID_CLASS),
            $this->em->getClassMetadata(self::SINGLE_STRING_ID_CLASS),
            $this->em->getClassMetadata(self::COMPOSITE_ID_CLASS),
            $this->em->getClassMetadata(self::GROUPABLE_CLASS),
        );

        try {
            $schemaTool->dropSchema($classes);
        } catch (\Exception $e) {
        }

        try {
            $schemaTool->createSchema($classes);
        } catch (\Exception $e) {
        }

        parent::setUp();
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->em = null;
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\StringCastException
     * @expectedMessage   Entity "Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity" passed to the choice field must have a "__toString()" method defined (or you can also override the "property" option).
     */
    public function testEntitiesMustHaveAToStringMethod()
    {
        $entity1 = new SingleIntIdNoToStringEntity(1, 'Foo');
        $entity2 = new SingleIntIdNoToStringEntity(2, 'Bar');

        // Persist for managed state
        $this->em->persist($entity1);
        $this->em->persist($entity2);

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_INT_ID_CLASS,
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
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        // no persist here!

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_INT_ID_CLASS,
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

    public function testInitExplicitChoices()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        // Persist for managed state
        $this->em->persist($entity1);
        $this->em->persist($entity2);

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_INT_ID_CLASS,
            'name',
            null,
            array(
                $entity1,
                $entity2,
            )
        );

        $this->assertSame(array(1 => $entity1, 2 => $entity2), $choiceList->getChoices());
    }

    public function testInitEmptyChoices()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        // Persist for managed state
        $this->em->persist($entity1);
        $this->em->persist($entity2);

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_INT_ID_CLASS,
            'name',
            null,
            array()
        );

        $this->assertSame(array(), $choiceList->getChoices());
    }

    public function testInitNestedChoices()
    {
        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Bar');

        // Oh yeah, we're persisting with fire now!
        $this->em->persist($entity1);
        $this->em->persist($entity2);

        $choiceList = new EntityChoiceList(
            $this->em,
            self::SINGLE_INT_ID_CLASS,
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
            'group2' => array(2 => new ChoiceView($entity2, '2', 'Bar')),
        ), $choiceList->getRemainingViews());
    }

    public function testGroupByPropertyPath()
    {
        $item1 = new GroupableEntity(1, 'Foo', 'Group1');
        $item2 = new GroupableEntity(2, 'Bar', 'Group1');
        $item3 = new GroupableEntity(3, 'Baz', 'Group2');
        $item4 = new GroupableEntity(4, 'Boo!', null);

        $this->em->persist($item1);
        $this->em->persist($item2);
        $this->em->persist($item3);
        $this->em->persist($item4);

        $choiceList = new EntityChoiceList(
            $this->em,
            self::GROUPABLE_CLASS,
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
            4 => new ChoiceView($item4, '4', 'Boo!'),
        ), $choiceList->getRemainingViews());
    }

    public function testGroupByInvalidPropertyPathReturnsFlatChoices()
    {
        $item1 = new GroupableEntity(1, 'Foo', 'Group1');
        $item2 = new GroupableEntity(2, 'Bar', 'Group1');

        $this->em->persist($item1);
        $this->em->persist($item2);

        $choiceList = new EntityChoiceList(
            $this->em,
            self::GROUPABLE_CLASS,
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
            2 => $item2,
        ), $choiceList->getChoices());
    }

    public function testInitShorthandEntityName()
    {
        $item1 = new SingleIntIdEntity(1, 'Foo');
        $item2 = new SingleIntIdEntity(2, 'Bar');

        $this->em->persist($item1);
        $this->em->persist($item2);

        $choiceList = new EntityChoiceList(
            $this->em,
            'SymfonyTestsDoctrine:SingleIntIdEntity'
        );

        $this->assertEquals(array(1, 2), $choiceList->getValuesForChoices(array($item1, $item2)));
    }

    public function testInitShorthandEntityName2()
    {
        $item1 = new SingleIntIdEntity(1, 'Foo');
        $item2 = new SingleIntIdEntity(2, 'Bar');

        $this->em->persist($item1);
        $this->em->persist($item2);

        $choiceList = new EntityChoiceList(
            $this->em,
            'SymfonyTestsDoctrine:SingleIntIdEntity'
        );

        $this->assertEquals(array(1, 2), $choiceList->getIndicesForChoices(array($item1, $item2)));
    }
}
