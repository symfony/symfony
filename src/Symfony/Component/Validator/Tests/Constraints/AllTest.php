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
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class AllTest extends TestCase
{
    public function testRejectNonConstraints()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new All([
            'foo',
        ]);
    }

    public function testRejectValidConstraint()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new All([
            new Valid(),
        ]);
    }

    public function testMissingConstraints()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The options "constraints" must be set for constraint "%s".', All::class));

        new All(null);
    }

    public function testMissingConstraintsDoctrineStyle()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage(\sprintf('The options "constraints" must be set for constraint "%s".', All::class));

        new All([]);
    }
}
