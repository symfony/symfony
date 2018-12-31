<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList\Factory;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\LazyChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

/**
 * Default implementation of {@link ChoiceListFactoryInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DefaultChoiceListFactory implements ChoiceListFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createListFromChoices($choices, $value = null)
    {
        return new ArrayChoiceList($choices, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function createListFromLoader(ChoiceLoaderInterface $loader, $value = null)
    {
        return new LazyChoiceList($loader, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function createView(ChoiceListInterface $list, $preferredChoices = null, $label = null, $index = null, $groupBy = null, $attr = null)
    {
        return (new ChoiceListViewBuilder())
            ->setPreferredChoices($preferredChoices)
            ->setLabel($label)
            ->setIndex($index)
            ->setGroupBy($groupBy)
            ->setAttr($attr)
            ->buildForList($list);
    }
}
