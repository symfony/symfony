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

use Symfony\Component\Validator\Constraints\DivisibleBy;
use Symfony\Component\Validator\Constraints\DivisibleByValidator;

/**
 * @author Colin O'Dell <colinodell@gmail.com>
 */
class DivisibleByValidatorTest extends AbstractComparisonValidatorTestCase
{
    protected function createValidator()
    {
        return new DivisibleByValidator();
    }

    protected function createConstraint(array $options = null)
    {
        return new DivisibleBy($options);
    }

    protected function getErrorCode()
    {
        return DivisibleBy::NOT_DIVISIBLE_BY;
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisons()
    {
        return array(
            array(-7, 1),
            array(0, 3.1415),
            array(42, 42),
            array(42, 21),
            array(3.25, 0.25),
            array('100', '10'),
            array(4.1, 0.1),
            array(-4.1, 0.1),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisonsToPropertyPath()
    {
        return array(
            array(25),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function provideInvalidComparisons()
    {
        return array(
            array(1, '1', 2, '2', 'integer'),
            array(10, '10', 3, '3', 'integer'),
            array(10, '10', 0, '0', 'integer'),
            array(42, '42', INF, 'INF', 'double'),
            array(4.15, '4.15', 0.1, '0.1', 'double'),
            array('22', '"22"', '10', '"10"', 'string'),
        );
    }
}
