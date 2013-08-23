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

use Symfony\Component\Translation\MessageSelector;

class MessageSelectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getChooseTests
     */
    public function testChoose($expected, $id, $number)
    {
        $selector = new MessageSelector();

        $this->assertEquals($expected, $selector->choose($id, $number, 'en'));
    }

    public function testReturnMessageIfExactlyOneStandardRuleIsGiven()
    {
        $selector = new MessageSelector();

        $this->assertEquals('There are two apples', $selector->choose('There are two apples', 2, 'en'));
    }

    /**
     * @dataProvider getNonMatchingMessages
     * @expectedException \InvalidArgumentException
     */
    public function testThrowExceptionIfMatchingMessageCannotBeFound($id, $number)
    {
        $selector = new MessageSelector();

        $selector->choose($id, $number, 'en');
    }

    public function getNonMatchingMessages()
    {
        return array(
            array('{0} There is no apple|{1} There is one apple', 2),
            array('{1} There is one apple|]1,Inf] There are %count% apples', 0),
            array('{1} There is one apple|]2,Inf] There are %count% apples', 2),
            array('{0} There is no apple|There is one apple', 2),
        );
    }

    public function getChooseTests()
    {
        return array(
            array('There is no apple', '{0} There is no apple|{1} There is one apple|]1,Inf] There are %count% apples', 0),
            array('There is no apple', '{0}     There is no apple|{1} There is one apple|]1,Inf] There are %count% apples', 0),
            array('There is no apple', '{0}There is no apple|{1} There is one apple|]1,Inf] There are %count% apples', 0),

            array('There is one apple', '{0} There is no apple|{1} There is one apple|]1,Inf] There are %count% apples', 1),

            array('There are %count% apples', '{0} There is no apple|{1} There is one apple|]1,Inf] There are %count% apples', 10),
            array('There are %count% apples', '{0} There is no apple|{1} There is one apple|]1,Inf]There are %count% apples', 10),
            array('There are %count% apples', '{0} There is no apple|{1} There is one apple|]1,Inf]     There are %count% apples', 10),

            array('There are %count% apples', 'There is one apple|There are %count% apples', 0),
            array('There is one apple', 'There is one apple|There are %count% apples', 1),
            array('There are %count% apples', 'There is one apple|There are %count% apples', 10),

            array('There are %count% apples', 'one: There is one apple|more: There are %count% apples', 0),
            array('There is one apple', 'one: There is one apple|more: There are %count% apples', 1),
            array('There are %count% apples', 'one: There is one apple|more: There are %count% apples', 10),

            array('There is no apple', '{0} There is no apple|one: There is one apple|more: There are %count% apples', 0),
            array('There is one apple', '{0} There is no apple|one: There is one apple|more: There are %count% apples', 1),
            array('There are %count% apples', '{0} There is no apple|one: There is one apple|more: There are %count% apples', 10),

            array('', '{0}|{1} There is one apple|]1,Inf] There are %count% apples', 0),
            array('', '{0} There is no apple|{1}|]1,Inf] There are %count% apples', 1),

            // Indexed only tests which are Gettext PoFile* compatible strings.
            array('There are %count% apples', 'There is one apple|There are %count% apples', 0),
            array('There is one apple', 'There is one apple|There are %count% apples', 1),
            array('There are %count% apples', 'There is one apple|There are %count% apples', 2),

            // Tests for float numbers
            array('There is almost one apple', '{0} There is no apple|]0,1[ There is almost one apple|{1} There is one apple|[1,Inf] There is more than one apple', 0.7),
            array('There is one apple', '{0} There is no apple|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 1),
            array('There is more than one apple', '{0} There is no apple|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 1.7),
            array('There is no apple', '{0} There is no apple|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 0),
            array('There is no apple', '{0} There is no apple|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 0.0),
            array('There is no apple', '{0.0} There is no apple|]0,1[There are %count% apples|{1} There is one apple|[1,Inf] There is more than one apple', 0),
        );
    }
}
