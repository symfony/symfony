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
use Symfony\Component\Translation\AltTranslator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class AltTranslatorTest extends TestCase
{
    public function testTrans()
    {
        $translator = new Translator('fr');
        $translator->setFallbackLocales(['en']);

        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['bar' => 'foobar'], 'en');

        $translator = new AltTranslator($translator);

        $this->assertSame('', $translator->trans(''));
        $this->assertSame('', $translator->trans(null));
        $this->assertSame('', $translator->trans([]));
        $this->assertSame('foobar', $translator->trans('bar'));
        $this->assertSame('foobar', $translator->trans(['bar']));
        $this->assertSame('foobar', $translator->trans(['foo', 'bar']));
        $this->assertSame('buz', $translator->trans(['foo', 'buz']));
    }
}
