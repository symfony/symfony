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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpunit;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

#[RequiresPhpunit('<10')]
final class ExpectDeprecationTraitTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * Do not remove this test in the next major version.
     *
     * @group legacy
     */
    #[Group('legacy')]
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
    #[Group('legacy')]
    #[RunInSeparateProcess]
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
    #[Group('legacy')]
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
    #[Group('legacy')]
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
    #[Group('legacy')]
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
