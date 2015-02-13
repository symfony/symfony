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

use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractEntityChoiceListSingleIntIdTest extends AbstractEntityChoiceListTest
{
    protected function getEntityClass()
    {
        return 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity';
    }

    /**
     * @return \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    protected function createObjects()
    {
        return array(
            new SingleIntIdEntity(-10, 'A'),
            new SingleIntIdEntity(10, 'B'),
            new SingleIntIdEntity(20, 'C'),
            new SingleIntIdEntity(30, 'D'),
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
