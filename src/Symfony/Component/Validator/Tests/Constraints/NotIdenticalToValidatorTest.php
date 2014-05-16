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

use Symfony\Component\Validator\Constraints\NotIdenticalTo;
use Symfony\Component\Validator\Constraints\NotIdenticalToValidator;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class NotIdenticalToValidatorTest extends AbstractComparisonValidatorTestCase
{
    protected function createValidator()
    {
        return new NotIdenticalToValidator();
    }

    protected function createConstraint(array $options)
    {
        return new NotIdenticalTo($options);
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisons()
    {
        return array(
            array(1, 2),
            array('2', 2),
            array('22', '333'),
            array(new \DateTime('2001-01-01'), new \DateTime('2000-01-01')),
            array(new \DateTime('2000-01-01'), new \DateTime('2000-01-01')),
            array(null, 1),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function provideInvalidComparisons()
    {
        $date = new \DateTime('2000-01-01');
        $object = new ComparisonTest_Class(2);

        return array(
            array(3, 3, '3', 'integer'),
            array('a', 'a', "'a'", 'string'),
            array($date, $date, '2000-01-01 00:00:00', 'DateTime'),
            array($object, $object, '2', __NAMESPACE__.'\ComparisonTest_Class'),
        );
    }
}
