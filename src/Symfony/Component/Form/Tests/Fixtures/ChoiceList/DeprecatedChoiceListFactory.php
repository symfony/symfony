<?php

namespace Symfony\Component\Form\Tests\Fixtures\ChoiceList;

use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;

class DeprecatedChoiceListFactory implements ChoiceListFactoryInterface
{
    public function createListFromChoices(iterable $choices, callable $value = null): ChoiceListInterface
    {
    }

    public function createListFromLoader(ChoiceLoaderInterface $loader, callable $value = null): ChoiceListInterface
    {
    }

    public function createView(ChoiceListInterface $list, $preferredChoices = null, $label = null, callable $index = null, callable $groupBy = null, $attr = null): ChoiceListView
    {
    }
}
