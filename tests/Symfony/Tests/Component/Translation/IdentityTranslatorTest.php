<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Translation;

use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\MessageSelector;

class IdentityTranslatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getTransTests
     */
    public function testTrans($expected, $id, $parameters)
    {
        $translator = new IdentityTranslator(new MessageSelector());

        $this->assertEquals($expected, $translator->trans($id, $parameters));
    }

    /**
     * @dataProvider getTransChoiceTests
     */
    public function testTransChoice($expected, $id, $number, $parameters)
    {
        $translator = new IdentityTranslator(new MessageSelector());

        $this->assertEquals($expected, $translator->transChoice($id, $number, $parameters));
    }

    // noop
    public function testGetSetLocale()
    {
        $translator = new IdentityTranslator(new MessageSelector());
        $translator->setLocale('en');
        $translator->getLocale();
    }

    public function getTransTests()
    {
        return array(
            array('Symfony2 is great!', 'Symfony2 is great!', array()),
            array('Symfony2 is awesome!', 'Symfony2 is %what%!', array('%what%' => 'awesome')),
        );
    }

    public function getTransChoiceTests()
    {
        return array(
            array('There is 10 apples', '{0} There is no apples|{1} There is one apple|]1,Inf] There is %count% apples', 10, array('%count%' => 10)),
        );
    }
}
