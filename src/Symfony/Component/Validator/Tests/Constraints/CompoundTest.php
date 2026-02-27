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
use Symfony\Component\Validator\Constraints\Compound;

class CompoundTest extends TestCase
{
    public function testGroupsAndPayload()
    {
        $payload = new \stdClass();
        $compound = new EmptyCompound(groups: ['my-group', 'my-other-group'], payload: $payload);

        $this->assertSame(['my-group', 'my-other-group'], $compound->groups);
        $this->assertSame($payload, $compound->payload);
    }
}

class EmptyCompound extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [];
    }
}
