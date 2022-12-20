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
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CollectionTest extends TestCase
{
    public function testRejectInvalidFieldsOption()
    {
        self::expectException(ConstraintDefinitionException::class);
        new Collection([
            'fields' => 'foo',
        ]);
    }

    public function testRejectNonConstraints()
    {
        self::expectException(ConstraintDefinitionException::class);
        new Collection([
            'foo' => 'bar',
        ]);
    }

    public function testRejectValidConstraint()
    {
        self::expectException(ConstraintDefinitionException::class);
        new Collection([
            'foo' => new Valid(),
        ]);
    }

    public function testRejectValidConstraintWithinOptional()
    {
        self::expectException(ConstraintDefinitionException::class);
        new Collection([
            'foo' => new Optional(new Valid()),
        ]);
    }

    public function testRejectValidConstraintWithinRequired()
    {
        self::expectException(ConstraintDefinitionException::class);
        new Collection([
            'foo' => new Required(new Valid()),
        ]);
    }

    public function testAcceptOptionalConstraintAsOneElementArray()
    {
        $collection1 = new Collection([
            'fields' => [
                'alternate_email' => [
                    new Optional(new Email()),
                ],
            ],
        ]);

        $collection2 = new Collection([
            'fields' => [
                'alternate_email' => new Optional(new Email()),
            ],
        ]);

        self::assertEquals($collection1, $collection2);
    }

    public function testAcceptRequiredConstraintAsOneElementArray()
    {
        $collection1 = new Collection([
            'fields' => [
                'alternate_email' => [
                    new Required(new Email()),
                ],
            ],
        ]);

        $collection2 = new Collection([
            'fields' => [
                'alternate_email' => new Required(new Email()),
            ],
        ]);

        self::assertEquals($collection1, $collection2);
    }

    public function testConstraintHasDefaultGroupWithOptionalValues()
    {
        $constraint = new Collection([
            'foo' => new Required(),
            'bar' => new Optional(),
        ]);

        self::assertEquals(['Default'], $constraint->groups);
        self::assertEquals(['Default'], $constraint->fields['foo']->groups);
        self::assertEquals(['Default'], $constraint->fields['bar']->groups);
    }
}
