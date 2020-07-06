<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList\Factory\Cache;

use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeInterface;

/**
 * A cacheable wrapper for {@see FormTypeInterface} or {@see FormTypeExtensionInterface}
 * which configures a "choice_loader" option.
 *
 * @internal
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
final class ChoiceLoader extends AbstractStaticOption implements ChoiceLoaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function loadChoiceList(callable $value = null)
    {
        return $this->getOption()->loadChoiceList($value);
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoicesForValues(array $values, callable $value = null)
    {
        return $this->getOption()->loadChoicesForValues($values, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function loadValuesForChoices(array $choices, callable $value = null)
    {
        return $this->getOption()->loadValuesForChoices($choices, $value);
    }
}
