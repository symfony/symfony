<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\IntlExtension;

/**
 * @author Jules Pietri <jules@heahprod.com>
 */
class IntlExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getLocalizedCountries
     */
    public function testLocalizedCountry($template, $result)
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Array(array('template' => $template)), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new IntlExtension('en'));

        try {
            $nodes = $twig->render('template');
            $this->assertSame($result, $nodes);
        } catch (\Twig_Error_Runtime $e) {
            throw $e->getPrevious();
        }
    }

    public function getLocalizedCountries()
    {
        return array(
            array('{{ "DE"|localized_country }}', 'Germany'),
            array('{{ "FR"|localized_country }}', 'France'),
            array('{{ "US"|localized_country }}', 'United States'),
            array('{{ ""|localized_country }}', ''),
        );
    }

    /**
     * @dataProvider getLocalizedCurrencies
     */
    public function testLocalizedCurrency($template, $result)
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Array(array('template' => $template)), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new IntlExtension('en'));

        try {
            $nodes = $twig->render('template');
            $this->assertSame($result, $nodes);
        } catch (\Twig_Error_Runtime $e) {
            throw $e->getPrevious();
        }
    }

    public function getLocalizedCurrencies()
    {
        return array(
            array('{{ 1000|localized_currency("EUR") }}', '€1,000.00'),
            array('{{ 1000|localized_currency("USD") }}', '$1,000.00'),
            array('{{ 1000|localized_currency("GBP") }}', '£1,000.00'),
        );
    }

    /**
     * @dataProvider getLocalizedCurrencyNames
     */
    public function testLocalizedCurrencyNames($template, $result)
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Array(array('template' => $template)), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new IntlExtension('en'));

        try {
            $nodes = $twig->render('template');
            $this->assertSame($result, $nodes);
        } catch (\Twig_Error_Runtime $e) {
            throw $e->getPrevious();
        }
    }

    public function getLocalizedCurrencyNames()
    {
        return array(
            array('{{ "EUR"|localized_currency_name }}', 'Euro'),
            array('{{ "USD"|localized_currency_name }}', 'US Dollar'),
            array('{{ "GBP"|localized_currency_name }}', 'British Pound'),
            array('{{ ""|localized_currency_name }}', ''),
        );
    }

    /**
     * @dataProvider getCurrencySymbols
     */
    public function testCurrencySymbol($template, $result)
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Array(array('template' => $template)), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new IntlExtension('en'));

        try {
            $nodes = $twig->render('template');
            $this->assertSame($result, $nodes);
        } catch (\Twig_Error_Runtime $e) {
            throw $e->getPrevious();
        }
    }

    public function getCurrencySymbols()
    {
        return array(
            array('{{ "EUR"|currency_symbol }}', '€'),
            array('{{ "USD"|currency_symbol }}', '$'),
            array('{{ "GBP"|currency_symbol }}', '£'),
            array('{{ ""|currency_symbol }}', ''),
        );
    }

    /**
     * @dataProvider getLocalizedDate
     */
    public function testLocalizedDate($template, $result)
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Array(array('template' => $template)), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new IntlExtension('en'));

        try {
            $nodes = $twig->render('template');
            $this->assertSame($result, $nodes);
        } catch (\Twig_Error_Runtime $e) {
            throw $e->getPrevious();
        }
    }

    public function getLocalizedDate()
    {
        return array(
            array('{{ "2001-01-01"|localized_date }}', 'Jan 1, 2001, 12:00:00 AM'),
            array('{{ "2001-01-01"|localized_date("short", "short") }}', '1/1/01, 12:00 AM'),
            array('{{ "2001-01-01"|localized_date(format="E y-M-d h:m:s") }}', 'Mon 2001-1-1 12:0:0'),
        );
    }

    /**
     * @dataProvider getWrongLocalizedDate
     */
    public function testLocalizedDateWithInvalidValues($template, $exceptionClass)
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Array(array('template' => $template)), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new IntlExtension('en'));

        $this->setExpectedException($exceptionClass);

        try {
            $twig->render('template');
        } catch (\Twig_Error_Runtime $e) {
            throw $e->getPrevious();
        }
    }

    public function getWrongLocalizedDate()
    {
        return array(
            array('{{ "www"|localized_date }}', '\Exception'),
            array('{{ ""|localized_date(dateFormat="www") }}', '\InvalidArgumentException'),
            array('{{ ""|localized_date(timeFormat="www") }}', '\InvalidArgumentException'),
        );
    }

    /**
     * @dataProvider getLocalizedLanguages
     */
    public function testLocalizedLanguage($template, $result)
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Array(array('template' => $template)), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new IntlExtension('en'));

        try {
            $nodes = $twig->render('template');
            $this->assertSame($result, $nodes);
        } catch (\Twig_Error_Runtime $e) {
            throw $e->getPrevious();
        }
    }

    public function getLocalizedLanguages()
    {
        return array(
            array('{{ "de"|localized_language }}', 'German'),
            array('{{ "fr"|localized_language }}', 'French'),
            array('{{ "en"|localized_language }}', 'English'),
            array('{{ "en_US"|localized_language }}', 'American English'),
            array('{{ "en-US"|localized_language }}', 'American English'),
            array('{{ ""|localized_language }}', ''),
        );
    }

    /**
     * @dataProvider getLocalizedLocales
     */
    public function testLocalizedLocale($template, $result)
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Array(array('template' => $template)), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new IntlExtension('en'));

        try {
            $nodes = $twig->render('template');
            $this->assertSame($result, $nodes);
        } catch (\Twig_Error_Runtime $e) {
            throw $e->getPrevious();
        }
    }

    public function getLocalizedLocales()
    {
        return array(
            array('{{ "de"|localized_locale }}', 'German'),
            array('{{ "fr"|localized_locale }}', 'French'),
            array('{{ "en"|localized_locale }}', 'English'),
            array('{{ "en_US"|localized_locale }}', 'English (United States)'),
            array('{{ ""|localized_locale }}', ''),
        );
    }

    /**
     * @dataProvider getLocalizedNumbers
     */
    public function testLocalizedNumber($template, $result)
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Array(array('template' => $template)), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new IntlExtension('en'));

        try {
            $nodes = $twig->render('template');
            $this->assertSame($result, $nodes);
        } catch (\Twig_Error_Runtime $e) {
            throw $e->getPrevious();
        }
    }

    public function getLocalizedNumbers()
    {
        return array(
            array('{{ 500000000|localized_number }}', '500,000,000'),
            array('{{ ""|localized_number }}', '0'),
        );
    }

    /**
     * @dataProvider getWrongLocalizedNumbers
     * @expectedException \InvalidArgumentException
     */
    public function testLocalizedNumbersWithInvalidValues($template)
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Array(array('template' => $template)), array('debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0));
        $twig->addExtension(new IntlExtension('en'));

        try {
            $twig->render('template');
        } catch (\Twig_Error_Runtime $e) {
            throw $e->getPrevious();
        }
    }

    public function getWrongLocalizedNumbers()
    {
        return array(
            array('{{ 1|localized_number("www") }}'),
            array('{{ 1|localized_number(style="www") }}'),
        );
    }
}
