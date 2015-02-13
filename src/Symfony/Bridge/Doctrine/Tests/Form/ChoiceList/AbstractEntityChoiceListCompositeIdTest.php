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

use Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIntIdEntity;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractEntityChoiceListCompositeIdTest extends AbstractEntityChoiceListTest
{
    protected function getEntityClass()
    {
        return 'Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIntIdEntity';
    }

    /**
     * @return \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    protected function createObjects()
    {
        return array(
            new CompositeIntIdEntity(10, 11, 'A'),
            new CompositeIntIdEntity(20, 21, 'B'),
            new CompositeIntIdEntity(30, 31, 'C'),
            new CompositeIntIdEntity(40, 41, 'D'),
        );
    }

    protected function getChoices()
    {
        return array(0 => $this->obj1, 1 => $this->obj2, 2 => $this->obj3, 3 => $this->obj4);
    }

    protected function getLabels()
    {
        return array(0 => 'A', 1 => 'B', 2 => 'C', 3 => 'D');
    }

    protected function getValues()
    {
        return array(0 => '0', 1 => '1', 2 => '2', 3 => '3');
    }

    protected function getIndices()
    {
        return array(0, 1, 2, 3);
    }
}
