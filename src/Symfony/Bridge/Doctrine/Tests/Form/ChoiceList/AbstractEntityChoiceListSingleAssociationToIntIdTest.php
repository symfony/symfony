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

use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleAssociationToIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdNoToStringEntity;

if (!class_exists('Symfony\Component\Form\Tests\Extension\Core\ChoiceList\AbstractChoiceListTest')) {
    return;
}

/**
 * Test choices generated from an entity with a primary foreign key.
 *
 * @author Premi Giorgio <giosh94mhz@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @group legacy
 */
abstract class AbstractEntityChoiceListSingleAssociationToIntIdTest extends AbstractEntityChoiceListTest
{
    protected function getEntityClass()
    {
        return 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleAssociationToIntIdEntity';
    }

    protected function getClassesMetadata()
    {
        return array(
            $this->em->getClassMetadata($this->getEntityClass()),
            $this->em->getClassMetadata('Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdNoToStringEntity'),
        );
    }

    protected function createChoiceList()
    {
        return new EntityChoiceList($this->em, $this->getEntityClass(), 'name');
    }

    /**
     * @return \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    protected function createObjects()
    {
        $innerA = new SingleIntIdNoToStringEntity(-10, 'inner_A');
        $innerB = new SingleIntIdNoToStringEntity(10, 'inner_B');
        $innerC = new SingleIntIdNoToStringEntity(20, 'inner_C');
        $innerD = new SingleIntIdNoToStringEntity(30, 'inner_D');

        $this->em->persist($innerA);
        $this->em->persist($innerB);
        $this->em->persist($innerC);
        $this->em->persist($innerD);

        return array(
            new SingleAssociationToIntIdEntity($innerA, 'A'),
            new SingleAssociationToIntIdEntity($innerB, 'B'),
            new SingleAssociationToIntIdEntity($innerC, 'C'),
            new SingleAssociationToIntIdEntity($innerD, 'D'),
        );
    }

    protected function getChoices()
    {
        return array('_10' => $this->obj1, 10 => $this->obj2, 20 => $this->obj3, 30 => $this->obj4);
    }

    protected function getLabels()
    {
        return array('_10' => 'A', 10 => 'B', 20 => 'C', 30 => 'D');
    }

    protected function getValues()
    {
        return array('_10' => '-10', 10 => '10', 20 => '20', 30 => '30');
    }

    protected function getIndices()
    {
        return array('_10', 10, 20, 30);
    }
}
