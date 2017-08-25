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

use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdEntity;

if (!class_exists('Symfony\Component\Form\Tests\Extension\Core\ChoiceList\AbstractChoiceListTest')) {
    return;
}

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractEntityChoiceListSingleStringIdTest extends AbstractEntityChoiceListTest
{
    protected function getEntityClass()
    {
        return 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdEntity';
    }

    /**
     * @return \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    protected function createObjects()
    {
        return array(
            new SingleStringIdEntity('a', 'A'),
            new SingleStringIdEntity('b', 'B'),
            new SingleStringIdEntity('c', 'C'),
            new SingleStringIdEntity('d', 'D'),
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
        return array(0 => 'a', 1 => 'b', 2 => 'c', 3 => 'd');
    }

    protected function getIndices()
    {
        return array(0, 1, 2, 3);
    }
}
