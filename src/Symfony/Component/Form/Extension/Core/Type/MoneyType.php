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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Extension\Core\DataTransformer\MoneyToLocalizedStringTransformer;
use Symfony\Component\Form\FormView;

class MoneyType extends AbstractType
{
    private static $patterns = array();

    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->appendClientTransformer(new MoneyToLocalizedStringTransformer($options['precision'], $options['grouping'], null, $options['divisor']))
            ->setAttribute('currency', $options['currency']);
    }

    public function buildView(FormView $view, FormInterface $form)
    {
        $view->set('money_pattern', self::getPattern($form->getAttribute('currency')));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'precision' => 2,
            'grouping' => false,
            'divisor' => 1,
            'currency' => 'EUR',
        );
    }

    public function getParent(array $options)
    {
        return 'field';
    }

    public function getName()
    {
        return 'money';
    }

    /**
     * Returns the pattern for this locale
     *
     * The pattern contains the placeholder "{{ widget }}" where the HTML tag should
     * be inserted
     */
    private static function getPattern($currency)
    {
        if (!$currency) {
            return '{{ widget }}';
        }

        if (!isset(self::$patterns[\Locale::getDefault()])) {
            self::$patterns[\Locale::getDefault()] = array();
        }

        if (!isset(self::$patterns[\Locale::getDefault()][$currency])) {
            $format = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::CURRENCY);
            $pattern = $format->formatCurrency('123', $currency);

            // the spacings between currency symbol and number are ignored, because
            // a single space leads to better readability in combination with input
            // fields

            // the regex also considers non-break spaces (0xC2 or 0xA0 in UTF-8)

            preg_match('/^([^\s\xc2\xa0]*)[\s\xc2\xa0]*123[,.]00[\s\xc2\xa0]*([^\s\xc2\xa0]*)$/', $pattern, $matches);

            if (!empty($matches[1])) {
                self::$patterns[\Locale::getDefault()] = $matches[1].' {{ widget }}';
            } else if (!empty($matches[2])) {
                self::$patterns[\Locale::getDefault()] = '{{ widget }} '.$matches[2];
            } else {
                self::$patterns[\Locale::getDefault()] = '{{ widget }}';
            }
        }

        return self::$patterns[\Locale::getDefault()];
    }
}
