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
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
class AtLeastOneOfTest extends TestCase
{
    public function testRejectNonConstraints()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        new AtLeastOneOf([
            'foo',
        ]);
    }

    public function testRejectValidConstraint()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');
        new AtLeastOneOf([
            new Valid(),
        ]);
    }
}
