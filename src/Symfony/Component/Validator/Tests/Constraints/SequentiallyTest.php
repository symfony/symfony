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
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

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

    public function testRejectValidConstraint()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The constraint Valid cannot be nested inside constraint "Symfony\Component\Validator\Constraints\Sequentially"');
        new Sequentially([
            new Valid(),
        ]);
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
