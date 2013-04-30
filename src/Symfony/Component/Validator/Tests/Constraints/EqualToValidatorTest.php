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

use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\EqualToValidator;
use Symfony\Component\Validator\Tests\Constraints\AbstractComparisonValidatorTestCase;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class EqualToValidatorTest extends AbstractComparisonValidatorTestCase
{
    protected function createValidator()
    {
        return new EqualToValidator();
    }

    protected function createConstraint(array $options)
    {
        return new EqualTo($options);
    }

    /**
     * {@inheritDoc}
     */
    public function provideValidComparisons()
    {
        return array(
            array(3, 3),
            array(3, '3'),
            array('a', 'a'),
            array(new \DateTime('2000-01-01'), new \DateTime('2000-01-01'))
        );
    }

    /**
     * {@inheritDoc}
     */
    public function provideInvalidComparisons()
    {
        return array(
            array(1, 2, '2', 'integer'),
            array('22', '333', "'333'", 'string'),
            array(new \DateTime('2001-01-01'), new \DateTime('2000-01-01'), '2000-01-01 00:00:00', 'DateTime')
        );
    }
}
