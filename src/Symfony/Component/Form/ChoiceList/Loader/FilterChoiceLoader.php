<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList\Loader;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class FilterChoiceLoader implements ChoiceLoaderInterface
{
    private $loader;
    private $filter;
    private $choiceList;

    public function __construct(ChoiceLoaderInterface $loader, callable $filter)
    {
        $this->loader = $loader;
        $this->filter = $filter;
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null)
    {
        if (null !== $this->choiceList) {
            return $this->choiceList;
        }

        $choiceList = $this->loader->loadChoiceList($value);
        $structured = $choiceList->getStructuredValues();
        $choices = $choiceList->getChoices();
        $visitor = function (array $list) use ($choices, &$visitor) {
            foreach ($list as $k => $v) {
                if (\is_array($v)) {
                    if ($v = $visitor($v)) {
                        $list[$k] = $v;
                    } else {
                        unset($list[$k]);
                    }
                    continue;
                }

                $choice = $choices[$v] ?? $v;
                if (!\call_user_func($this->filter, $choice)) {
                    unset($list[$k]);
                    continue;
                }

                $list[$k] = $choice;
            }

            return $list;
        };

        return $this->choiceList = new ArrayChoiceList($visitor($structured), $value);
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoicesForValues(array $values, $value = null)
    {
        // Optimize
        if (empty($values)) {
            return [];
        }
        if (null !== $this->choiceList) {
            return $this->choiceList->getChoicesForValues($values);
        }

        return array_filter($this->loader->loadChoicesForValues($values, $value), $this->filter);
    }

    /**
     * {@inheritdoc}
     */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        // Optimize
        if (empty($choices)) {
            return [];
        }
        if (null !== $this->choiceList) {
            return $this->choiceList->getValuesForChoices($choices);
        }

        return $this->loader->loadValuesForChoices(array_filter($choices, $this->filter), $value);
    }
}
