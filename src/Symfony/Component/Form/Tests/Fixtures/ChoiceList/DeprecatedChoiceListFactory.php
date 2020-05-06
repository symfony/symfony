<?php

namespace Symfony\Component\Form\Tests\Fixtures\ChoiceList;

use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class DeprecatedChoiceListFactory implements ChoiceListFactoryInterface
{
    public function createListFromChoices(iterable $choices, callable $value = null)
    {
    }

    public function createListFromLoader(ChoiceLoaderInterface $loader, callable $value = null)
    {
    }

    public function createView(ChoiceListInterface $list, $preferredChoices = null, $label = null, callable $index = null, callable $groupBy = null, $attr = null)
    {
    }
}
