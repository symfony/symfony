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

final class ExpectedDeprecationAnnotationTest extends TestCase
{
    /**
     * Do not remove this test in the next major versions.
     *
     * @group legacy
     *
     * @expectedDeprecation foo
     */
    public function testOne()
    {
        @trigger_error('foo', E_USER_DEPRECATED);
    }

    /**
     * Do not remove this test in the next major versions.
     *
     * @group legacy
     *
     * @expectedDeprecation foo
     * @expectedDeprecation bar
     */
    public function testMany()
    {
        @trigger_error('foo', E_USER_DEPRECATED);
        @trigger_error('bar', E_USER_DEPRECATED);
    }
}
