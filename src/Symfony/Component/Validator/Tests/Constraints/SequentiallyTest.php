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
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class SequentiallyTest extends TestCase
{
    public function testRejectNonConstraints()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The value "foo" is not an instance of Constraint in constraint "Symfony\Component\Validator\Constraints\Sequentially"');
        new Sequentially([
            'foo',
        ]);
    }

    public function testAcceptValidConstraint()
    {
        $valid = new Valid();
        $constraint = new Sequentially([$valid]);

        $this->assertSame([$valid], $constraint->constraints);
        $this->assertSame(['Default'], $constraint->groups);
        $this->assertNull($valid->groups);
    }

    public function testValidWithoutGroupsDoesNotInheritExplicitGroups()
    {
        $type = new Type('object');
        $valid = new Valid();
        $constraint = new Sequentially([$type, $valid], groups: ['custom']);

        $this->assertSame(['custom'], $constraint->groups);
        $this->assertSame(['custom'], $type->groups);
        $this->assertNull($valid->groups);
    }

    public function testRejectValidWithExplicitGroups()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The constraint Valid cannot define groups when nested inside constraint "Symfony\\Component\\Validator\\Constraints\\Sequentially". Set the groups on the Sequentially constraint instead.');

        new Sequentially([new Valid(groups: ['custom'])]);
    }

    public function testRejectValidWithExplicitTriggerGroups()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The constraint Valid cannot define groups when nested inside constraint "Symfony\\Component\\Validator\\Constraints\\Sequentially". Set the groups on the Sequentially constraint instead.');

        new Sequentially([new Valid(groups: ['trigger'], restrictGroups: false)], groups: ['trigger']);
    }

    public function testAcceptValidWithoutGroupsAndUnrestrictedCascading()
    {
        $valid = new Valid(restrictGroups: false);
        $constraint = new Sequentially([$valid], groups: ['custom']);

        $this->assertSame(['custom'], $constraint->groups);
        $this->assertNull($valid->groups);
    }

    public function testImplicitGroupNameIsNotForwardedToValidWithoutGroups()
    {
        $valid = new Valid();
        $constraint = new Sequentially([$valid]);

        $constraint->addImplicitGroupName('ImplicitGroup');

        $this->assertSame(['Default', 'ImplicitGroup'], $constraint->groups);
        $this->assertNull($valid->groups);
    }

    public function testSerializationPreservesValidWithoutGroups()
    {
        $constraint = unserialize(serialize(new Sequentially([new Valid()], groups: ['custom'])));

        $this->assertSame(['custom'], $constraint->groups);
        $this->assertNull($constraint->constraints[0]->groups);
    }

    public function testValidInSequentiallyCannotBeAppliedAtClassLevel()
    {
        $metadata = new ClassMetadata(self::class);

        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The constraint "Symfony\Component\Validator\Constraints\Valid" cannot be put on classes.');

        $metadata->addConstraint(new Sequentially([new Valid()]));
    }

    public function testMissingConstraints()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The options "constraints" must be set for constraint "%s".', Sequentially::class));

        new Sequentially(null);
    }

    public function testMissingConstraintsDoctrineStyle()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The options "constraints" must be set for constraint "%s".', Sequentially::class));

        new Sequentially([]);
    }
}
