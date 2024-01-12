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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\DefaultParametersTranslator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Translator;

class DefaultParametersTranslatorTest extends TestCase
{
    public function testTrans()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['welcome' => 'Welcome {name}!'], 'en');
        $translator->addResource('array', ['welcome' => 'Bienvenue {name}!'], 'fr');

        $defaultParametersTranslator = new DefaultParametersTranslator($translator, ['{name}' => 'Global name']);

        $this->assertSame('Welcome Global name!', $defaultParametersTranslator->trans('welcome'));
        $this->assertSame('Bienvenue Global name!', $defaultParametersTranslator->trans('welcome', [], null, 'fr'));
        $this->assertSame('Welcome John!', $defaultParametersTranslator->trans('welcome', ['{name}' => 'John']));
        $this->assertSame('Bienvenue Jean!', $defaultParametersTranslator->trans('welcome', ['{name}' => 'Jean'], null, 'fr'));
    }

    public function testTransICU()
    {
        if (!class_exists(\MessageFormatter::class)) {
            $this->markTestSkipped(sprintf('Skipping test as the required "%s" class does not exist. Consider installing the "intl" PHP extension or the "symfony/polyfill-intl-messageformatter" package.', \MessageFormatter::class));
        }

        $domain = 'test.'.MessageCatalogue::INTL_DOMAIN_SUFFIX;

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'apples' => '{apples, plural, =0 {There are no apples} one {There is one apple} other {There are # apples}}',
        ], 'en', $domain);

        $defaultParametersTranslator = new DefaultParametersTranslator($translator, ['{apples}' => 42]);

        $this->assertSame('There are 42 apples', $defaultParametersTranslator->trans('apples', [], $domain));
        $this->assertSame('There is one apple', $defaultParametersTranslator->trans('apples', ['{apples}' => 1], $domain));
    }

    public function testGetLocale()
    {
        $translator = new Translator('en');
        $defaultParametersTranslator = new DefaultParametersTranslator($translator, []);

        $this->assertSame('en', $defaultParametersTranslator->getLocale());
    }
}
