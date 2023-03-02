<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

final class ExpectDeprecationTraitTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * Do not remove this test in the next major version.
     *
     * @group legacy
     */
    public function testOne()
    {
        $this->expectDeprecation('foo');
        @trigger_error('foo', \E_USER_DEPRECATED);
    }

    /**
     * Do not remove this test in the next major version.
     *
     * @group legacy
     *
     * @runInSeparateProcess
     */
    public function testOneInIsolation()
    {
        $this->expectDeprecation('foo');
        @trigger_error('foo', \E_USER_DEPRECATED);
    }

    /**
     * Do not remove this test in the next major version.
     *
     * @group legacy
     */
    public function testMany()
    {
        $this->expectDeprecation('foo');
        $this->expectDeprecation('bar');
        @trigger_error('foo', \E_USER_DEPRECATED);
        @trigger_error('bar', \E_USER_DEPRECATED);
    }

    /**
     * Do not remove this test in the next major version.
     *
     * @group legacy
     *
     * @expectedDeprecation foo
     */
    public function testOneWithAnnotation()
    {
        $this->expectDeprecation('bar');
        @trigger_error('foo', \E_USER_DEPRECATED);
        @trigger_error('bar', \E_USER_DEPRECATED);
    }

    /**
     * Do not remove this test in the next major version.
     *
     * @group legacy
     *
     * @expectedDeprecation foo
     * @expectedDeprecation bar
     */
    public function testManyWithAnnotation()
    {
        $this->expectDeprecation('ccc');
        $this->expectDeprecation('fcy');
        @trigger_error('foo', \E_USER_DEPRECATED);
        @trigger_error('bar', \E_USER_DEPRECATED);
        @trigger_error('ccc', \E_USER_DEPRECATED);
        @trigger_error('fcy', \E_USER_DEPRECATED);
    }
}
