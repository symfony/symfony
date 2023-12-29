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
use Symfony\Component\Translation\GlobalsTranslator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class GlobalTranslatorTest extends TestCase
{
    public function testTrans()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['test' => 'Welcome %name%!'], 'en');

        $globalTranslator = new GlobalsTranslator($translator);
        $globalTranslator->addGlobal('%name%', 'Global name');

        $this->assertSame('Welcome Global name!', $globalTranslator->trans('test'));
        $this->assertSame('Welcome Name!', $globalTranslator->trans('test', ['%name%' => 'Name']));
    }

    public function testGetLocale()
    {
        $translator = new Translator('en');
        $globalTranslator = new GlobalsTranslator($translator);

        $this->assertSame('en', $globalTranslator->getLocale());
    }
}
