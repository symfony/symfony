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

use Symfony\Component\Form\Util\FormUtil;

class FormUtilTest extends \PHPUnit_Framework_TestCase
{
    public function isChoiceGroupProvider()
    {
        return array(
            array(false, 0),
            array(false, '0'),
            array(false, '1'),
            array(false, 1),
            array(false, ''),
            array(false, null),
            array(false, true),

            array(true, array()),
        );
    }

    /**
     * @dataProvider isChoiceGroupProvider
     */
    public function testIsChoiceGroup($expected, $value)
    {
        $this->assertSame($expected, FormUtil::isChoiceGroup($value));
    }

    public function testIsChoiceGroupPart2()
    {
        if (version_compare(PHP_VERSION, '5.3.2') <= 0) {
            $this->markTestSkipped('PHP prior to 5.3.3 has issue with SplFixedArrays - https://bugs.php.net/bug.php?id=50481');
        }

        $this->assertSame(true, FormUtil::isChoiceGroup(new \SplFixedArray(1)));
    }

    public function isChoiceSelectedProvider()
    {
        // The commented cases should not be necessary anymore, because the
        // choice lists should assure that both values passed here are always
        // strings
        return array(
//             array(true, 0, 0),
            array(true, '0', '0'),
            array(true, '1', '1'),
//             array(true, false, 0),
//             array(true, true, 1),
            array(true, '', ''),
//             array(true, null, ''),
            array(true, '1.23', '1.23'),
            array(true, 'foo', 'foo'),
            array(true, 'foo10', 'foo10'),
            array(true, 'foo', array(1, 'foo', 'foo10')),

            array(false, 10, array(1, 'foo', 'foo10')),
            array(false, 0, array(1, 'foo', 'foo10')),
        );
    }

    /**
     * @dataProvider isChoiceSelectedProvider
     */
    public function testIsChoiceSelected($expected, $choice, $value)
    {
        $this->assertSame($expected, FormUtil::isChoiceSelected($choice, $value));
    }
}
