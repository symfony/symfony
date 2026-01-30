<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Tests\Fixtures\ClassConstraint;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;

class ConstraintTest extends TestCase
{
    public function testAddDefaultGroupAddsGroup(): void
    {
        $constraint = new ConstraintA(null, null, ['Default']);
        $constraint->addImplicitGroupName('Foo');
        $this->assertEquals(['Default', 'Foo'], $constraint->groups);
    }

    public function testGetTargetsCanBeString(): void
    {
        $constraint = new ClassConstraint();

        $this->assertEquals('class', $constraint->getTargets());
    }

    public function testGetTargetsCanBeArray(): void
    {
        $constraint = new ConstraintA();

        $this->assertEquals(['property', 'class'], $constraint->getTargets());
    }

    public function testSerialize(): void
    {
        $constraint = new ConstraintA('foo', 'bar');

        $restoredConstraint = unserialize(serialize($constraint));

        $this->assertEquals($constraint, $restoredConstraint);
    }

    public function testSerializeInitializesGroupsOptionToDefault(): void
    {
        $constraint = new ConstraintA('foo', 'bar');

        $constraint = unserialize(serialize($constraint));

        $expected = new ConstraintA('foo', 'bar', ['Default']);

        $this->assertEquals($expected, $constraint);
    }

    public function testSerializeKeepsCustomGroups(): void
    {
        $constraint = new ConstraintA('foo', 'bar', ['MyGroup']);

        $constraint = unserialize(serialize($constraint));

        $this->assertSame(['MyGroup'], $constraint->groups);
    }

    public function testGetErrorNameForUnknownCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Constraint::getErrorName(1);
    }
}
