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

use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqualValidator;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class LessThanOrEqualValidatorTest extends AbstractComparisonValidatorTestCase
{
    protected function createValidator()
    {
        return new LessThanOrEqualValidator();
    }

    protected function createConstraint(array $options)
    {
        return new LessThanOrEqual($options);
    }

    /**
     * {@inheritDoc}
     */
    public function provideValidComparisons()
    {
        return array(
            array(1, 2),
            array(1, 1),
            array(new \DateTime('2000-01-01'), new \DateTime('2000-01-01')),
            array(new \DateTime('2000-01-01'), new \DateTime('2020-01-01')),
            array(new ComparisonTest_Class(4), new ComparisonTest_Class(5)),
            array(new ComparisonTest_Class(5), new ComparisonTest_Class(5)),
            array('a', 'a'),
            array('a', 'z'),
            array(null, 1),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function provideInvalidComparisons()
    {
        return array(
            array(2, 1, '1', 'integer'),
            array(new \DateTime('2010-01-01'), new \DateTime('2000-01-01'), '2000-01-01 00:00:00', 'DateTime'),
            array(new ComparisonTest_Class(5), new ComparisonTest_Class(4), '4', __NAMESPACE__.'\ComparisonTest_Class'),
            array('c', 'b', "'b'", 'string')
        );
    }
}
