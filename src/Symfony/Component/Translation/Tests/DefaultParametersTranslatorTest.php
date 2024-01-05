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
use Symfony\Component\Translation\Translator;

class DefaultParametersTranslatorTest extends TestCase
{
    public function testTrans()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['test' => 'Welcome %name%!'], 'en');

        $defaultParametersTranslator = new DefaultParametersTranslator($translator);
        $defaultParametersTranslator->addDefaultParameter('%name%', 'Global name');

        $this->assertSame('Welcome Global name!', $defaultParametersTranslator->trans('test'));
        $this->assertSame('Welcome Name!', $defaultParametersTranslator->trans('test', ['%name%' => 'Name']));
    }

    public function testGetLocale()
    {
        $translator = new Translator('en');
        $defaultParametersTranslator = new DefaultParametersTranslator($translator);

        $this->assertSame('en', $defaultParametersTranslator->getLocale());
    }
}
