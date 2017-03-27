<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Symfony\Component\Image\Tests\Constraint\IsImageEqual;

class TestCase extends PHPUnitTestCase
{
    const HTTP_IMAGE = 'http://symfony.com/images/common/logo/logo_symfony_header.png';

    private static $supportMockingImagick;

    /**
     * Asserts that two images are equal using color histogram comparison method
     *
     * @param ImageInterface $expected
     * @param ImageInterface $actual
     * @param string         $message
     * @param float          $delta
     * @param integer        $buckets
     */
    public static function assertImageEquals($expected, $actual, $message = '', $delta = 0.1, $buckets = 4)
    {
        $constraint = new IsImageEqual($expected, $delta, $buckets);

        self::assertThat($actual, $constraint, $message);
    }

    public function setExpectedException($exception, $message = null, $code = null)
    {
        if (method_exists(parent::class, 'expectException')) {
            parent::expectException($exception);
            if (null !== $message) {
                parent::expectExceptionMessage($message);
            }
            if (null !== $code) {
                parent::expectExceptionCode($code);
            }
        } else {
            return parent::setExpectedException($exception, $message, $code);
        }
    }

    /**
     * Actually it's not possible on some HHVM versions
     */
    protected function supportsMockingImagick()
    {
        if (null !== self::$supportMockingImagick) {
            return self::$supportMockingImagick;
        }

        try {
            @$this->getMockBuilder('\Imagick')->disableOriginalConstructor()->getMock();
        } catch (\Exception $e) {
            return self::$supportMockingImagick = false;
        }

        return self::$supportMockingImagick = true;
    }
}
