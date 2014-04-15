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

use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\IdenticalToValidator;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class IdenticalToValidatorTest extends AbstractComparisonValidatorTestCase
{
    protected function createValidator()
    {
        return new IdenticalToValidator();
    }

    protected function createConstraint(array $options)
    {
        return new IdenticalTo($options);
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisons()
    {
        $date = new \DateTime('2000-01-01');

        return array(
            array(3, 3),
            array('a', 'a'),
            array($date, $date),
            array(null, 1),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function provideInvalidComparisons()
    {
        return array(
            array(1, 2, '2', 'integer'),
            array(2, '2', "'2'", 'string'),
            array('22', '333', "'333'", 'string'),
            array(new \DateTime('2001-01-01'), new \DateTime('2001-01-01'), '2001-01-01 00:00:00', 'DateTime'),
            array(new \DateTime('2001-01-01'), new \DateTime('1999-01-01'), '1999-01-01 00:00:00', 'DateTime')
        );
    }
}
