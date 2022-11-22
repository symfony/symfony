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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Util\IcuVersion;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IcuVersionTest extends TestCase
{
    public static function normalizeProvider()
    {
        return [
            [null, '1', '10'],
            [null, '1.2', '12'],
            [null, '1.2.3', '12.3'],
            [null, '1.2.3.4', '12.3.4'],
            [1, '1', '10'],
            [1, '1.2', '12'],
            [1, '1.2.3', '12'],
            [1, '1.2.3.4', '12'],
            [2, '1', '10'],
            [2, '1.2', '12'],
            [2, '1.2.3', '12.3'],
            [2, '1.2.3.4', '12.3'],
            [3, '1', '10'],
            [3, '1.2', '12'],
            [3, '1.2.3', '12.3'],
            [3, '1.2.3.4', '12.3.4'],
        ];
    }

    /**
     * @dataProvider normalizeProvider
     */
    public function testNormalize($precision, $version, $result)
    {
        $this->assertSame($result, IcuVersion::normalize($version, $precision));
    }

    public static function compareProvider()
    {
        return [
            [null, '1', '==', '1', true],
            [null, '1.0', '==', '1.1', false],
            [null, '1.0.0', '==', '1.0.1', false],
            [null, '1.0.0.0', '==', '1.0.0.1', false],
            [null, '1.0.0.0.0', '==', '1.0.0.0.1', false],

            [null, '1', '==', '10', true],
            [null, '1.0', '==', '11', false],
            [null, '1.0.0', '==', '10.1', false],
            [null, '1.0.0.0', '==', '10.0.1', false],
            [null, '1.0.0.0.0', '==', '10.0.0.1', false],

            [1, '1', '==', '1', true],
            [1, '1.0', '==', '1.1', false],
            [1, '1.0.0', '==', '1.0.1', true],
            [1, '1.0.0.0', '==', '1.0.0.1', true],
            [1, '1.0.0.0.0', '==', '1.0.0.0.1', true],

            [1, '1', '==', '10', true],
            [1, '1.0', '==', '11', false],
            [1, '1.0.0', '==', '10.1', true],
            [1, '1.0.0.0', '==', '10.0.1', true],
            [1, '1.0.0.0.0', '==', '10.0.0.1', true],

            [2, '1', '==', '1', true],
            [2, '1.0', '==', '1.1', false],
            [2, '1.0.0', '==', '1.0.1', false],
            [2, '1.0.0.0', '==', '1.0.0.1', true],
            [2, '1.0.0.0.0', '==', '1.0.0.0.1', true],

            [2, '1', '==', '10', true],
            [2, '1.0', '==', '11', false],
            [2, '1.0.0', '==', '10.1', false],
            [2, '1.0.0.0', '==', '10.0.1', true],
            [2, '1.0.0.0.0', '==', '10.0.0.1', true],

            [3, '1', '==', '1', true],
            [3, '1.0', '==', '1.1', false],
            [3, '1.0.0', '==', '1.0.1', false],
            [3, '1.0.0.0', '==', '1.0.0.1', false],
            [3, '1.0.0.0.0', '==', '1.0.0.0.1', true],

            [3, '1', '==', '10', true],
            [3, '1.0', '==', '11', false],
            [3, '1.0.0', '==', '10.1', false],
            [3, '1.0.0.0', '==', '10.0.1', false],
            [3, '1.0.0.0.0', '==', '10.0.0.1', true],
        ];
    }

    /**
     * @dataProvider compareProvider
     */
    public function testCompare($precision, $version1, $operator, $version2, $result)
    {
        $this->assertSame($result, IcuVersion::compare($version1, $version2, $operator, $precision));
    }
}
