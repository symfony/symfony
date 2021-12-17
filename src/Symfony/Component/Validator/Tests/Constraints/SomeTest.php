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
use Symfony\Component\Validator\Constraints\Some;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
class SomeTest extends TestCase
{
    public function testRejectNonConstraints()
    {
        $this->expectException(ConstraintDefinitionException::class);

        new Some(['foo']);
    }

    public function testRejectValidConstraint()
    {
        $this->expectException(ConstraintDefinitionException::class);

        new Some([new Valid()]);
    }

    public function testThrowsWithMinLessThanZero()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "min" option must be greater than 0.');

        new Some(['min' => -1, 'constraints' => []]);
    }

    public function testCustomMinMessage()
    {
        $constraint = new Some(['constraints' => [], 'minMessage' => 'One value should:']);

        self::assertSame('One value should:', $constraint->minMessage);
    }

    public function testCustomMaxMessage()
    {
        $constraint = new Some(['constraints' => [], 'maxMessage' => 'Two values should:']);

        self::assertSame('Two values should:', $constraint->maxMessage);
    }

    public function testCustomExactlyMessage()
    {
        $constraint = new Some(['constraints' => [], 'exactMessage' => 'Exactly 3 values should:']);

        self::assertSame('Exactly 3 values should:', $constraint->exactMessage);
    }
}
