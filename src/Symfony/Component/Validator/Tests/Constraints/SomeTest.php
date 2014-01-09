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

use Symfony\Component\Validator\Constraints\Some;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * @author Marc Morera Merino <hyuhu@mmoreram.com>
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 *
 * @api
 */
class SomeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testRejectNonConstraints()
    {
        new Some(array(
            'foo',
        ));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testRejectValidConstraint()
    {
        new Some(array(
            new Valid(),
        ));
    }
}
