<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\AbstractComparison;

class AbstractComparisonTest extends TestCase
{
    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testConstructorWithArrayOption()
    {
        $comparison = new class(['value' => 42, 'message' => 'my error']) extends AbstractComparison {};

        $this->assertSame(42, $comparison->value);
        $this->assertSame('my error', $comparison->message);
    }
}
