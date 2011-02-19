<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Locale\Stub;

require_once __DIR__.'/../TestCase.php';

use Symfony\Component\Locale\Locale;
use Symfony\Component\Locale\Stub\StubCollator;
use Symfony\Tests\Component\Locale\TestCase as LocaleTestCase;

class StubCollatorTest extends LocaleTestCase
{
    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException
     */
    public function testConstructorWithUnsupportedLocale()
    {
        $collator = new StubCollator('pt_BR');
    }

    /**
    * @dataProvider asortProvider
    */
    public function testAsortStub($array, $sortFlag, $expected)
    {
        $collator = new StubCollator('en');
        $collator->asort($array, $sortFlag);
        $this->assertSame($expected, $array);
    }

    /**
    * @dataProvider asortProvider
    */
    public function testAsortIntl($array, $sortFlag, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $collator = new \Collator('en');
        $collator->asort($array, $sortFlag);
        $this->assertSame($expected, $array);
    }

    public function asortProvider()
    {
        return array(
            /* array, sortFlag, expected */
            array(
                array('a', 'b', 'c'),
                StubCollator::SORT_REGULAR,
                array('a', 'b', 'c'),
            ),
            array(
                array('c', 'b', 'a'),
                StubCollator::SORT_REGULAR,
                array(2 => 'a', 1 => 'b',  0 => 'c'),
            ),
            array(
                array('b', 'c', 'a'),
                StubCollator::SORT_REGULAR,
                array(2 => 'a', 0 => 'b', 1 => 'c'),
            ),
        );
    }

    public function testStaticCreate()
    {
        $collator = StubCollator::create('en');
        $this->assertInstanceOf('Symfony\Component\Locale\Stub\StubCollator', $collator);
    }
}
