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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\GroupSequenceProvider;
use Symfony\Component\Validator\Tests\Dummy\DummyGroupProvider;

class GroupSequenceProviderTest extends TestCase
{
    public function testCreateAttributeStyle()
    {
        $sequence = new GroupSequenceProvider(provider: DummyGroupProvider::class);

        $this->assertSame(DummyGroupProvider::class, $sequence->provider);
    }
}
