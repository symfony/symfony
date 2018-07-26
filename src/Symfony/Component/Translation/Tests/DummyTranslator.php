<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests;

use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Component\Translation\TranslatorInterface;

class DummyTranslator implements TranslatorInterface, TranslatorBagInterface
{
    private $locale;
    private $fallbackLocales = array();

    /**
     * @param $locale
     * @param string[] $fallbackLocales
     */
    public function __construct($locale = null, array $fallbackLocales = array())
    {
        $this->locale = $locale;
        $this->fallbackLocales = $fallbackLocales;
    }

    public function getCatalogue($locale = null)
    {
    }

    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
    }

    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function getFallbackLocales()
    {
        return $this->fallbackLocales;
    }
}
