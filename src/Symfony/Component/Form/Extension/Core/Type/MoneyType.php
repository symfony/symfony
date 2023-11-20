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
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\DataTransformer\MoneyToLocalizedStringTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MoneyType extends AbstractType
{
    protected static array $patterns = [];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Values used in HTML5 number inputs should be formatted as in "1234.5", ie. 'en' format without grouping,
        // according to https://www.w3.org/TR/html51/sec-forms.html#date-time-and-number-formats
        $builder
            ->addViewTransformer(new MoneyToLocalizedStringTransformer(
                $options['scale'],
                $options['grouping'],
                $options['rounding_mode'],
                $options['divisor'],
                $options['html5'] ? 'en' : null
            ))
        ;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['money_pattern'] = self::getPattern($options['currency']);

        if ($options['html5']) {
            $view->vars['type'] = 'number';
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'scale' => 2,
            'grouping' => false,
            'rounding_mode' => \NumberFormatter::ROUND_HALFUP,
            'divisor' => 1,
            'currency' => 'EUR',
            'compound' => false,
            'html5' => false,
            'invalid_message' => 'Please enter a valid money amount.',
        ]);

        $resolver->setAllowedValues('rounding_mode', [
            \NumberFormatter::ROUND_FLOOR,
            \NumberFormatter::ROUND_DOWN,
            \NumberFormatter::ROUND_HALFDOWN,
            \NumberFormatter::ROUND_HALFEVEN,
            \NumberFormatter::ROUND_HALFUP,
            \NumberFormatter::ROUND_UP,
            \NumberFormatter::ROUND_CEILING,
        ]);

        $resolver->setAllowedTypes('scale', 'int');

        $resolver->setAllowedTypes('html5', 'bool');

        $resolver->setNormalizer('grouping', static function (Options $options, $value) {
            if ($value && $options['html5']) {
                throw new LogicException('Cannot use the "grouping" option when the "html5" option is enabled.');
            }

            return $value;
        });
    }

    public function getBlockPrefix(): string
    {
        return 'money';
    }

    /**
     * Returns the pattern for this locale in UTF-8.
     *
     * The pattern contains the placeholder "{{ widget }}" where the HTML tag should
     * be inserted
     */
    protected static function getPattern(?string $currency): string
    {
        if (!$currency) {
            return '{{ widget }}';
        }

        $locale = \Locale::getDefault();

        if (!isset(self::$patterns[$locale])) {
            self::$patterns[$locale] = [];
        }

        if (!isset(self::$patterns[$locale][$currency])) {
            $format = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $pattern = $format->formatCurrency('123', $currency);

            // the spacings between currency symbol and number are ignored, because
            // a single space leads to better readability in combination with input
            // fields

            // the regex also considers non-break spaces (0xC2 or 0xA0 in UTF-8)

            preg_match('/^([^\s\xc2\xa0]*)[\s\xc2\xa0]*123(?:[,.]0+)?[\s\xc2\xa0]*([^\s\xc2\xa0]*)$/u', $pattern, $matches);

            if (!empty($matches[1])) {
                self::$patterns[$locale][$currency] = $matches[1].' {{ widget }}';
            } elseif (!empty($matches[2])) {
                self::$patterns[$locale][$currency] = '{{ widget }} '.$matches[2];
            } else {
                self::$patterns[$locale][$currency] = '{{ widget }}';
            }
        }

        return self::$patterns[$locale][$currency];
    }
}
