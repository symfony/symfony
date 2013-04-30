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

use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqualValidator;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class GreaterThanOrEqualValidatorTest extends AbstractComparisonValidatorTestCase
{
    protected function createValidator()
    {
        return new GreaterThanOrEqualValidator();
    }

    protected function createConstraint(array $options)
    {
        return new GreaterThanOrEqual($options);
    }

    /**
     * {@inheritDoc}
     */
    public function provideValidComparisons()
    {
        return array(
            array(3, 2),
            array(1, 1),
            array(new \DateTime('2010/01/01'), new \DateTime('2000/01/01')),
            array(new \DateTime('2000/01/01'), new \DateTime('2000/01/01')),
            array('a', 'a'),
            array('z', 'a'),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function provideInvalidComparisons()
    {
        return array(
            array(1, 2, '2', 'integer'),
            array(new \DateTime('2000/01/01'), new \DateTime('2005/01/01'), '2005-01-01 00:00:00', 'DateTime'),
            array('b', 'c', "'c'", 'string')
        );
    }
}
