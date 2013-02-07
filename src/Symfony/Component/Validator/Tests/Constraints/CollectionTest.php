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

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Collection\Required;
use Symfony\Component\Validator\Constraints\Collection\Optional;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CollectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testRejectInvalidFieldsOption()
    {
        new Collection(array(
            'fields' => 'foo',
        ));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testRejectNonConstraints()
    {
        new Collection(array(
            'foo' => 'bar',
        ));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testRejectValidConstraint()
    {
        new Collection(array(
            'foo' => new Valid(),
        ));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testRejectValidConstraintWithinOptional()
    {
        new Collection(array(
            'foo' => new Optional(new Valid()),
        ));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testRejectValidConstraintWithinRequired()
    {
        new Collection(array(
            'foo' => new Required(new Valid()),
        ));
    }
}
