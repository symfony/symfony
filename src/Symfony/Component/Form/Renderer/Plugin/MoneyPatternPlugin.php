<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\Plugin;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Renderer\RendererInterface;

class MoneyPatternPlugin implements RendererPluginInterface
{
    private static $patterns = array();

    private $currency = 'EUR';

    public function __construct($currency)
    {
        $this->currency = $currency;
    }

    public function setUp(FormInterface $field, RendererInterface $renderer)
    {
        $renderer->setVar('money_pattern', self::getPattern($this->currency));
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