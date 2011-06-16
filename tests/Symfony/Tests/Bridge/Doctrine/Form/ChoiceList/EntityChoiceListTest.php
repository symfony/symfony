<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Doctrine\Form\ChoiceList;

require_once __DIR__.'/../DoctrineOrmTestCase.php';
require_once __DIR__.'/../../Fixtures/SingleIdentEntity.php';

use Symfony\Tests\Bridge\Doctrine\Form\DoctrineOrmTestCase;
use Symfony\Tests\Bridge\Doctrine\Form\Fixtures\SingleIdentEntity;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;

class EntityChoiceListTest extends DoctrineOrmTestCase
{
    const SINGLE_IDENT_CLASS = 'Symfony\Tests\Bridge\Doctrine\Form\Fixtures\SingleIdentEntity';

    const COMPOSITE_IDENT_CLASS = 'Symfony\Tests\Bridge\Doctrine\Form\Fixtures\CompositeIdentEntity';

    private $em;

    protected function setUp()
    {
        parent::setUp();

        $this->em = $this->createTestEntityManager();
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->em = null;
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
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

        $this->assertSame(array(1 => 'Foo', 2 => 'Bar'), $choiceList->getChoices());
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
            )
        );

        $this->assertSame(array(
            'group1' => array(1 => 'Foo'),
            'group2' => array(2 => 'Bar')
        ), $choiceList->getChoices());
    }
}
