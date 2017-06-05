<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Intl\Intl;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LocaleType extends AbstractType implements ChoiceLoaderInterface
{
    /**
     * Locale loaded choice list.
     *
     * The choices are lazy loaded and generated from the Intl component.
     *
     * {@link \Symfony\Component\Intl\Intl::getLocaleBundle()}.
     *
     * @var ArrayChoiceList
     */
    private $choiceList;

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'choice_loader' => $this,
            'choice_translation_domain' => false,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return __NAMESPACE__.'\ChoiceType';
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'locale';
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null)
    {
        if (null !== $this->choiceList) {
            return $this->choiceList;
        }

        return $this->choiceList = new ArrayChoiceList(array_flip(Intl::getLocaleBundle()->getLocaleNames()), $value);
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoicesForValues(array $values, $value = null)
    {
        // Optimize
        $values = array_filter($values);
        if (empty($values)) {
            return array();
        }

        // If no callable is set, values are the same as choices
        if (null === $value) {
            return $values;
        }

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        // Optimize
        $choices = array_filter($choices);
        if (empty($choices)) {
            return array();
        }

        // If no callable is set, choices are the same as values
        if (null === $value) {
            return $choices;
        }

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }
}
