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
use Symfony\Component\Form\ChoiceList\Loader\IntlCallbackChoiceLoader;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\Options;
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
     *
     * @deprecated since Symfony 4.1
     */
    private $choiceList;

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choice_loader' => function (Options $options) {
                $choiceTranslationLocale = $options['choice_translation_locale'];

                return new IntlCallbackChoiceLoader(function () use ($choiceTranslationLocale) {
                    return array_flip(Locales::getNames($choiceTranslationLocale));
                });
            },
            'choice_translation_domain' => false,
            'choice_translation_locale' => null,
        ]);

        $resolver->setAllowedTypes('choice_translation_locale', ['null', 'string']);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return ChoiceType::class;
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
     *
     * @deprecated since Symfony 4.1
     */
    public function loadChoiceList($value = null)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.1, use the "choice_loader" option instead.', __METHOD__), \E_USER_DEPRECATED);

        if (null !== $this->choiceList) {
            return $this->choiceList;
        }

        return $this->choiceList = new ArrayChoiceList(array_flip(Locales::getNames()), $value);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since Symfony 4.1
     */
    public function loadChoicesForValues(array $values, $value = null)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.1, use the "choice_loader" option instead.', __METHOD__), \E_USER_DEPRECATED);

        // Optimize
        $values = array_filter($values);
        if (empty($values)) {
            return [];
        }

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since Symfony 4.1
     */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.1, use the "choice_loader" option instead.', __METHOD__), \E_USER_DEPRECATED);

        // Optimize
        $choices = array_filter($choices);
        if (empty($choices)) {
            return [];
        }

        // If no callable is set, choices are the same as values
        if (null === $value) {
            return $choices;
        }

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }
}
