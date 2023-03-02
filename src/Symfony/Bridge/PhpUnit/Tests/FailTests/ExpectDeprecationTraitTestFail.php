<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests\FailTests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

/**
 * Class ExpectDeprecationTraitTestFail.
 *
 * This class is deliberately suffixed with *TestFail.php so that it is ignored
 * by PHPUnit. This test is designed to fail. See ../expectdeprecationfail.phpt.
 */
final class ExpectDeprecationTraitTestFail extends TestCase
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
        @trigger_error('bar', \E_USER_DEPRECATED);
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
        @trigger_error('bar', \E_USER_DEPRECATED);
    }
}
