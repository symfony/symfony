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

use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Contracts\Tests\Translation\TranslatorTest;

class IdentityTranslatorTest extends TranslatorTest
{
    public function getTranslator()
    {
        return new IdentityTranslator();
    }

    /**
     * @dataProvider getTransChoiceTests
     * @group legacy
     */
    public function testTransChoiceWithExplicitLocale($expected, $id, $number, $parameters)
    {
        $translator = $this->getTranslator();
        $translator->setLocale('en');

        $this->assertEquals($expected, $translator->transChoice($id, $number, $parameters));
    }

    /**
     * @dataProvider getTransChoiceTests
     * @group legacy
     */
    public function testTransChoiceWithDefaultLocale($expected, $id, $number, $parameters)
    {
        \Locale::setDefault('en');

        $translator = $this->getTranslator();

        $this->assertEquals($expected, $translator->transChoice($id, $number, $parameters));
    }

    /**
     * @dataProvider getInternal
     * @group legacy
     */
    public function testInterval($expected, $number, $interval)
    {
        $translator = $this->getTranslator();

        $this->assertEquals($expected, $translator->transChoice($interval.' foo|[1,Inf[ bar', $number));
    }

    /**
     * @dataProvider getChooseTests
     * @group legacy
     */
    public function testChoose($expected, $id, $number)
    {
        $translator = $this->getTranslator();

        $this->assertEquals($expected, $translator->transChoice($id, $number));
    }

    /**
     * @group legacy
     */
    public function testReturnMessageIfExactlyOneStandardRuleIsGiven()
    {
        $translator = $this->getTranslator();

        $this->assertEquals('There are two apples', $translator->transChoice('There are two apples', 2));
    }

    /**
     * @dataProvider getNonMatchingMessages
     * @expectedException \InvalidArgumentException
     * @group legacy
     */
    public function testThrowExceptionIfMatchingMessageCannotBeFound($id, $number)
    {
        $translator = $this->getTranslator();

        $translator->transChoice($id, $number);
    }

    public function getTransChoiceTests()
    {
        return array(
            array('There are no apples', '{0} There are no apples|{1} There is one apple|]1,Inf] There are %count% apples', 0, array('%count%' => 0)),
            array('There is one apple', '{0} There are no apples|{1} There is one apple|]1,Inf] There are %count% apples', 1, array('%count%' => 1)),
            array('There are 10 apples', '{0} There are no apples|{1} There is one apple|]1,Inf] There are %count% apples', 10, array('%count%' => 10)),
            array('There are 0 apples', 'There is 1 apple|There are %count% apples', 0, array('%count%' => 0)),
            array('There is 1 apple', 'There is 1 apple|There are %count% apples', 1, array('%count%' => 1)),
            array('There are 10 apples', 'There is 1 apple|There are %count% apples', 10, array('%count%' => 10)),
            // custom validation messages may be coded with a fixed value
            array('There are 2 apples', 'There are 2 apples', 2, array('%count%' => 2)),
        );
    }

    public function getInternal()
    {
        return array(
            array('foo', 3, '{1,2, 3 ,4}'),
            array('bar', 10, '{1,2, 3 ,4}'),
            array('bar', 3, '[1,2]'),
            array('foo', 1, '[1,2]'),
            array('foo', 2, '[1,2]'),
            array('bar', 1, ']1,2['),
            array('bar', 2, ']1,2['),
            array('foo', log(0), '[-Inf,2['),
            array('foo', -log(0), '[-2,+Inf]'),
        );
    }

    public function getNonMatchingMessages()
    {
        return array(
            array('{0} There are no apples|{1} There is one apple', 2),
            array('{1} There is one apple|]1,Inf] There are %count% apples', 0),
            array('{1} There is one apple|]2,Inf] There are %count% apples', 2),
            array('{0} There are no apples|There is one apple', 2),
        );
    }

    public function getChooseTests()
    {
        return array(
            array('There are no apples', '{0} There are no apples|{1} There is one apple|]1,Inf] There are %count% apples', 0),
            array('There are no apples', '{0}     There are no apples|{1} There is one apple|]1,Inf] There are %count% apples', 0),
            array('There are no apples', '{0}There are no apples|{1} There is one apple|]1,Inf] There are %count% apples', 0),

            array('There is one apple', '{0} There are no apples|{1} There is one apple|]1,Inf] There are %count% apples', 1),

            array('There are %count% apples', '{0} There are no apples|{1} There is one apple|]1,Inf] There are %count% apples', 10),
            array('There are %count% apples', '{0} There are no apples|{1} There is one apple|]1,Inf]There are %count% apples', 10),
            array('There are %count% apples', '{0} There are no apples|{1} There is one apple|]1,Inf]     There are %count% apples', 10),

            array('There are %count% apples', 'There is one apple|There are %count% apples', 0),
            array('There is one apple', 'There is one apple|There are %count% apples', 1),
            array('There are %count% apples', 'There is one apple|There are %count% apples', 10),

            array('There are %count% apples', 'one: There is one apple|more: There are %count% apples', 0),
            array('There is one apple', 'one: There is one apple|more: There are %count% apples', 1),
            array('There are %count% apples', 'one: There is one apple|more: There are %count% apples', 10),

            array('There are no apples', '{0} There are no apples|one: There is one apple|more: There are %count% apples', 0),
            array('There is one apple', '{0} There are no apples|one: There is one apple|more: There are %count% apples', 1),
            array('There are %count% apples', '{0} There are no apples|one: There is one apple|more: There are %count% apples', 10),

            array('', '{0}|{1} There is one apple|]1,Inf] There are %count% apples', 0),
            array('', '{0} There are no apples|{1}|]1,Inf] There are %count% apples', 1),

            // Indexed only tests which are Gettext PoFile* compatible strings.
            array('There are %count% apples', 'There is one apple|There are %count% apples', 0),
            array('There is one apple', 'There is one apple|There are %count% apples', 1),
            array('There are %count% apples', 'There is one apple|There are %count% apples', 2),

            // Tests for float numbers
            array('There is almost one apple', '{0} There are no apples|]0,1[ There is almost one apple|{1} There is one apple|[1,Inf] There is more than one apple', 0.7),
            array('There is one apple', '{0} There are no apples|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 1),
            array('There is more than one apple', '{0} There are no apples|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 1.7),
            array('There are no apples', '{0} There are no apples|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 0),
            array('There are no apples', '{0} There are no apples|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 0.0),
            array('There are no apples', '{0.0} There are no apples|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 0),

            // Test texts with new-lines
            // with double-quotes and \n in id & double-quotes and actual newlines in text
            array("This is a text with a\n            new-line in it. Selector = 0.", '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.', 0),
            // with double-quotes and \n in id and single-quotes and actual newlines in text
            array("This is a text with a\n            new-line in it. Selector = 1.", '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.', 1),
            array("This is a text with a\n            new-line in it. Selector > 1.", '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.', 5),
            // with double-quotes and id split accros lines
            array('This is a text with a
            new-line in it. Selector = 1.', '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.', 1),
            // with single-quotes and id split accros lines
            array('This is a text with a
            new-line in it. Selector > 1.', '{0}This is a text with a
            new-line in it. Selector = 0.|{1}This is a text with a
            new-line in it. Selector = 1.|[1,Inf]This is a text with a
            new-line in it. Selector > 1.', 5),
            // with single-quotes and \n in text
            array('This is a text with a\nnew-line in it. Selector = 0.', '{0}This is a text with a\nnew-line in it. Selector = 0.|{1}This is a text with a\nnew-line in it. Selector = 1.|[1,Inf]This is a text with a\nnew-line in it. Selector > 1.', 0),
            // with double-quotes and id split accros lines
            array("This is a text with a\nnew-line in it. Selector = 1.", "{0}This is a text with a\nnew-line in it. Selector = 0.|{1}This is a text with a\nnew-line in it. Selector = 1.|[1,Inf]This is a text with a\nnew-line in it. Selector > 1.", 1),
            // esacape pipe
            array('This is a text with | in it. Selector = 0.', '{0}This is a text with || in it. Selector = 0.|{1}This is a text with || in it. Selector = 1.', 0),
            // Empty plural set (2 plural forms) from a .PO file
            array('', '|', 1),
            // Empty plural set (3 plural forms) from a .PO file
            array('', '||', 1),
        );
    }
}
