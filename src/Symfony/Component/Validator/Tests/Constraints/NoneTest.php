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
use Symfony\Component\Validator\Constraints\None;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
class NoneTest extends TestCase
{
    public function testRejectNonConstraints()
    {
        $this->expectException(ConstraintDefinitionException::class);

        new None(['foo']);
    }

    public function testRejectValidConstraint()
    {
        $this->expectException(ConstraintDefinitionException::class);

        new None([new Valid()]);
    }

    public function testCustomMessage()
    {
        $constraint = new None(['constraints' => [], 'message' => 'No values should:']);

        self::assertSame('No values should:', $constraint->message);
    }
}
