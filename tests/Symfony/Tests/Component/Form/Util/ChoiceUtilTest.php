<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Util;

use Symfony\Component\Form\Util\ChoiceUtil;

class ChoiceUtilTest extends \PHPUnit_Framework_TestCase
{
    public function toValidChoiceProvider()
    {
        return array(
            array(0, 0),
            array('0', 0),
            array('1', 1),
            array(false, 0),
            array(true, 1),
            array('', ''),
            array(null, ''),
            array('1.23', '1.23'),
            array('foo', 'foo'),
            array('foo10', 'foo10'),
        );
    }

    /**
     * @dataProvider toValidChoiceProvider
     */
    public function testToValidChoice($in, $out)
    {
        $this->assertSame($out, ChoiceUtil::toValidChoice($in));
    }

    public function testToValidChoiceArray()
    {
        $in = $out = array();

        foreach ($this->toValidChoiceProvider() as $call) {
            $in[] = $call[0];
            $out[] = $call[1];
        }

        $this->assertSame($out, ChoiceUtil::toValidChoiceArray($in));
    }
}