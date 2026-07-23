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
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

class CompoundTest extends TestCase
{
    public function testGroupsAndPayload()
    {
        $payload = new \stdClass();
        $compound = new EmptyCompound(groups: ['my-group', 'my-other-group'], payload: $payload);

        $this->assertSame(['my-group', 'my-other-group'], $compound->groups);
        $this->assertSame($payload, $compound->payload);
    }

    public function testGroupsArePropagatedToNestedCompositeConstraints()
    {
        $compound = new CompoundWithSequentially(groups: ['my-group']);

        $this->assertSame(['my-group'], $compound->groups);

        $sequentially = $compound->constraints[0];
        $this->assertInstanceOf(Sequentially::class, $sequentially);
        $this->assertSame(['my-group'], $sequentially->groups);

        foreach ($sequentially->constraints as $nestedConstraint) {
            $this->assertSame(['my-group'], $nestedConstraint->groups);
        }
    }

    public function testExplicitGroupsOnNestedCompositeArePreserved()
    {
        $compound = new CompoundWithExplicitlyGroupedSequentially(groups: ['outer', 'inner']);

        $this->assertSame(['outer', 'inner'], $compound->groups);

        $sequentially = $compound->constraints[0];
        $this->assertInstanceOf(Sequentially::class, $sequentially);
        $this->assertSame(['inner'], $sequentially->groups);

        foreach ($sequentially->constraints as $nestedConstraint) {
            $this->assertSame(['inner'], $nestedConstraint->groups);
        }
    }
}

class EmptyCompound extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [];
    }
}

class CompoundWithSequentially extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new Sequentially([
                new NotBlank(),
                new Length(min: 3),
            ]),
        ];
    }
}

class CompoundWithExplicitlyGroupedSequentially extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new Sequentially(constraints: [
                new NotBlank(),
                new Length(min: 3),
            ], groups: ['inner']),
        ];
    }
}
