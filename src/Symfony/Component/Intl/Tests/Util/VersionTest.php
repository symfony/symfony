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

use Symfony\Component\Intl\Util\Version;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class VersionTest extends \PHPUnit_Framework_TestCase
{
    public function normalizeProvider()
    {
        return array(
            array(null, '1', '1'),
            array(null, '1.2', '1.2'),
            array(null, '1.2.3', '1.2.3'),
            array(null, '1.2.3.4', '1.2.3.4'),
            array(1, '1', '1'),
            array(1, '1.2', '1'),
            array(1, '1.2.3', '1'),
            array(1, '1.2.3.4', '1'),
            array(2, '1', '1'),
            array(2, '1.2', '1.2'),
            array(2, '1.2.3', '1.2'),
            array(2, '1.2.3.4', '1.2'),
            array(3, '1', '1'),
            array(3, '1.2', '1.2'),
            array(3, '1.2.3', '1.2.3'),
            array(3, '1.2.3.4', '1.2.3'),
            array(4, '1', '1'),
            array(4, '1.2', '1.2'),
            array(4, '1.2.3', '1.2.3'),
            array(4, '1.2.3.4', '1.2.3.4'),
        );
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
        return array(
            array(null, '1', '==', '1', true),
            array(null, '1.0', '==', '1.1', false),
            array(null, '1.0.0', '==', '1.0.1', false),
            array(null, '1.0.0.0', '==', '1.0.0.1', false),

            array(1, '1', '==', '1', true),
            array(1, '1.0', '==', '1.1', true),
            array(1, '1.0.0', '==', '1.0.1', true),
            array(1, '1.0.0.0', '==', '1.0.0.1', true),

            array(2, '1', '==', '1', true),
            array(2, '1.0', '==', '1.1', false),
            array(2, '1.0.0', '==', '1.0.1', true),
            array(2, '1.0.0.0', '==', '1.0.0.1', true),

            array(3, '1', '==', '1', true),
            array(3, '1.0', '==', '1.1', false),
            array(3, '1.0.0', '==', '1.0.1', false),
            array(3, '1.0.0.0', '==', '1.0.0.1', true),
        );
    }

    /**
     * @dataProvider compareProvider
     */
    public function testCompare($precision, $version1, $operator, $version2, $result)
    {
        $this->assertSame($result, Version::compare($version1, $version2, $operator, $precision));
    }
}
