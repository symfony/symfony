<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Config\Definition;

use Symfony\Component\Config\Definition\Processor;

class ProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getKeyNormalizationTests
     */
    public function testNormalizeKeys($denormalized, $normalized)
    {
        $this->assertSame($normalized, Processor::normalizeKeys($denormalized));
    }

    public function getKeyNormalizationTests()
    {
        return array(
            array(
                array('foo-bar' => 'foo'),
                array('foo_bar' => 'foo'),
            ),
            array(
                array('foo-bar_moo' => 'foo'),
                array('foo-bar_moo' => 'foo'),
            ),
            array(
                array('foo-bar' => null, 'foo_bar' => 'foo'),
                array('foo-bar' => null, 'foo_bar' => 'foo'),
            ),
            array(
                array('foo-bar' => array('foo-bar' => 'foo')),
                array('foo_bar' => array('foo_bar' => 'foo')),
            ),
            array(
                array('foo_bar' => array('foo-bar' => 'foo')),
                array('foo_bar' => array('foo_bar' => 'foo')),
            )
        );
    }
}
