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
use Symfony\Component\Intl\Util\Version;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class VersionTest extends TestCase
{
    public function normalizeProvider()
    {
        return [
            [null, '1', '1'],
            [null, '1.2', '1.2'],
            [null, '1.2.3', '1.2.3'],
            [null, '1.2.3.4', '1.2.3.4'],
            [1, '1', '1'],
            [1, '1.2', '1'],
            [1, '1.2.3', '1'],
            [1, '1.2.3.4', '1'],
            [2, '1', '1'],
            [2, '1.2', '1.2'],
            [2, '1.2.3', '1.2'],
            [2, '1.2.3.4', '1.2'],
            [3, '1', '1'],
            [3, '1.2', '1.2'],
            [3, '1.2.3', '1.2.3'],
            [3, '1.2.3.4', '1.2.3'],
            [4, '1', '1'],
            [4, '1.2', '1.2'],
            [4, '1.2.3', '1.2.3'],
            [4, '1.2.3.4', '1.2.3.4'],
        ];
    }

    /**
     * @dataProvider normalizeProvider
     */
    public function testNormalize($precision, $version, $result)
    {
        $this->assertSame($result, Version::normalize($version, $precision));
    }

    public function compareProvider()
    {
        return [
            [null, '1', '==', '1', true],
            [null, '1.0', '==', '1.1', false],
            [null, '1.0.0', '==', '1.0.1', false],
            [null, '1.0.0.0', '==', '1.0.0.1', false],

            [1, '1', '==', '1', true],
            [1, '1.0', '==', '1.1', true],
            [1, '1.0.0', '==', '1.0.1', true],
            [1, '1.0.0.0', '==', '1.0.0.1', true],

            [2, '1', '==', '1', true],
            [2, '1.0', '==', '1.1', false],
            [2, '1.0.0', '==', '1.0.1', true],
            [2, '1.0.0.0', '==', '1.0.0.1', true],

            [3, '1', '==', '1', true],
            [3, '1.0', '==', '1.1', false],
            [3, '1.0.0', '==', '1.0.1', false],
            [3, '1.0.0.0', '==', '1.0.0.1', true],
        ];
    }

    /**
     * @dataProvider compareProvider
     */
    public function testCompare($precision, $version1, $operator, $version2, $result)
    {
        $this->assertSame($result, Version::compare($version1, $version2, $operator, $precision));
    }
}
