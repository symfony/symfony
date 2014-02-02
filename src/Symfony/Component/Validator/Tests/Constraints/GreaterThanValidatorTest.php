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

use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanValidator;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class GreaterThanValidatorTest extends AbstractComparisonValidatorTestCase
{
    protected function createValidator()
    {
        return new GreaterThanValidator();
    }

    protected function createConstraint(array $options)
    {
        return new GreaterThan($options);
    }

    /**
     * {@inheritDoc}
     */
    public function provideValidComparisons()
    {
        return array(
            array(2, 1),
            array(new \DateTime('2005/01/01'), new \DateTime('2001/01/01')),
            array(new ComparisonTest_Class(5), new ComparisonTest_Class(4)),
            array('333', '22'),
            array(null, 1),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function provideInvalidComparisons()
    {
        return array(
            array(1, 2, '2', 'integer'),
            array(2, 2, '2', 'integer'),
            array(new \DateTime('2000/01/01'), new \DateTime('2005/01/01'), '2005-01-01 00:00:00', 'DateTime'),
            array(new \DateTime('2000/01/01'), new \DateTime('2000/01/01'), '2000-01-01 00:00:00', 'DateTime'),
            array(new ComparisonTest_Class(4), new ComparisonTest_Class(5), '5', __NAMESPACE__.'\ComparisonTest_Class'),
            array(new ComparisonTest_Class(5), new ComparisonTest_Class(5), '5', __NAMESPACE__.'\ComparisonTest_Class'),
            array('22', '333', "'333'", 'string'),
            array('22', '22', "'22'", 'string')
        );
    }
}
