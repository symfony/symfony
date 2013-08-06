<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Util;

use Symfony\Component\Intl\Util\IcuVersion;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IcuVersionTest extends \PHPUnit_Framework_TestCase
{
    public function normalizeProvider()
    {
        return array(
            array(null, '1', '10'),
            array(null, '1.2', '12'),
            array(null, '1.2.3', '12.3'),
            array(null, '1.2.3.4', '12.3.4'),
            array(1, '1', '10'),
            array(1, '1.2', '12'),
            array(1, '1.2.3', '12'),
            array(1, '1.2.3.4', '12'),
            array(2, '1', '10'),
            array(2, '1.2', '12'),
            array(2, '1.2.3', '12.3'),
            array(2, '1.2.3.4', '12.3'),
            array(3, '1', '10'),
            array(3, '1.2', '12'),
            array(3, '1.2.3', '12.3'),
            array(3, '1.2.3.4', '12.3.4'),
        );
    }

    /**
     * @dataProvider normalizeProvider
     */
    public function testNormalize($precision, $version, $result)
    {
        $this->assertSame($result, IcuVersion::normalize($version, $precision));
    }

    public function compareProvider()
    {
        return array(
            array(null, '1', '==', '1', true),
            array(null, '1.0', '==', '1.1', false),
            array(null, '1.0.0', '==', '1.0.1', false),
            array(null, '1.0.0.0', '==', '1.0.0.1', false),
            array(null, '1.0.0.0.0', '==', '1.0.0.0.1', false),

            array(null, '1', '==', '10', true),
            array(null, '1.0', '==', '11', false),
            array(null, '1.0.0', '==', '10.1', false),
            array(null, '1.0.0.0', '==', '10.0.1', false),
            array(null, '1.0.0.0.0', '==', '10.0.0.1', false),

            array(1, '1', '==', '1', true),
            array(1, '1.0', '==', '1.1', false),
            array(1, '1.0.0', '==', '1.0.1', true),
            array(1, '1.0.0.0', '==', '1.0.0.1', true),
            array(1, '1.0.0.0.0', '==', '1.0.0.0.1', true),

            array(1, '1', '==', '10', true),
            array(1, '1.0', '==', '11', false),
            array(1, '1.0.0', '==', '10.1', true),
            array(1, '1.0.0.0', '==', '10.0.1', true),
            array(1, '1.0.0.0.0', '==', '10.0.0.1', true),

            array(2, '1', '==', '1', true),
            array(2, '1.0', '==', '1.1', false),
            array(2, '1.0.0', '==', '1.0.1', false),
            array(2, '1.0.0.0', '==', '1.0.0.1', true),
            array(2, '1.0.0.0.0', '==', '1.0.0.0.1', true),

            array(2, '1', '==', '10', true),
            array(2, '1.0', '==', '11', false),
            array(2, '1.0.0', '==', '10.1', false),
            array(2, '1.0.0.0', '==', '10.0.1', true),
            array(2, '1.0.0.0.0', '==', '10.0.0.1', true),

            array(3, '1', '==', '1', true),
            array(3, '1.0', '==', '1.1', false),
            array(3, '1.0.0', '==', '1.0.1', false),
            array(3, '1.0.0.0', '==', '1.0.0.1', false),
            array(3, '1.0.0.0.0', '==', '1.0.0.0.1', true),

            array(3, '1', '==', '10', true),
            array(3, '1.0', '==', '11', false),
            array(3, '1.0.0', '==', '10.1', false),
            array(3, '1.0.0.0', '==', '10.0.1', false),
            array(3, '1.0.0.0.0', '==', '10.0.0.1', true),
        );
    }

    /**
     * @dataProvider compareProvider
     */
    public function testCompare($precision, $version1, $operator, $version2, $result)
    {
        $this->assertSame($result, IcuVersion::compare($version1, $version2, $operator, $precision));
    }
}
