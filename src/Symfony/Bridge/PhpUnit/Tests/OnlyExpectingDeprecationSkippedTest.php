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
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * This test is meant to be skipped.
 *
 * @requires extension ext-dummy
 */
#[RequiresPhpExtension('ext-dummy')]
final class OnlyExpectingDeprecationSkippedTest extends TestCase
{
    /**
     * Do not remove this test in the next major versions.
     *
     * @group legacy
     *
     * @expectedDeprecation unreachable
     */
    #[Group('legacy')]
    public function testExpectingOnlyDeprecations()
    {
        $this->fail('should never be ran.');
    }
}
