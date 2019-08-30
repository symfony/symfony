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

use Symfony\Component\Validator\Constraint;
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

    protected function createConstraint(array $options = null): Constraint
    {
        return new NotIdenticalTo($options);
    }

    protected function getErrorCode(): ?string
    {
        return NotIdenticalTo::IS_IDENTICAL_ERROR;
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisons(): array
    {
        $negativeDateInterval = new \DateInterval('P2Y');
        $negativeDateInterval->invert = 1;

        return [
            [1, 2],
            ['2', 2],
            ['22', '333'],
            [new \DateTime('2001-01-01'), new \DateTime('2000-01-01')],
            [new \DateTime('2000-01-01'), new \DateTime('2000-01-01')],
            [new \DateTime('2001-01-01'), '2000-01-01'],
            [new \DateTime('2000-01-01'), '2000-01-01'],
            [new \DateTime('2001-01-01'), '2000-01-01'],
            [new \DateTime('2000-01-01 UTC'), '2000-01-01 UTC'],
            [null, 1],
            ['\DateInterval instance !== same string' => new \DateInterval('P22M'), '22 months', '+22 months', '1 year and 10 months', \DateInterval::class],
            ['negative \DateInterval instance !== same negative string' => $negativeDateInterval, '-2 years', '-2 years', '-2 years', \DateInterval::class],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [0],
        ];
    }

    public function provideAllInvalidComparisons(): array
    {
        $this->setDefaultTimezone('UTC');

        // Don't call addPhp5Dot5Comparisons() automatically, as it does
        // not take care of identical objects
        $comparisons = $this->provideInvalidComparisons();

        $this->restoreDefaultTimezone();

        return $comparisons;
    }

    /**
     * {@inheritdoc}
     */
    public function provideInvalidComparisons(): array
    {
        $date = new \DateTime('2000-01-01');
        $object = new ComparisonTest_Class(2);

        $negativeDateInterval = new \DateInterval('P2Y');
        $negativeDateInterval->invert = 1;

        $comparisons = [
            [3, '3', 3, '3', 'integer'],
            ['a', '"a"', 'a', '"a"', 'string'],
            [$date, 'Jan 1, 2000, 12:00 AM', $date, 'Jan 1, 2000, 12:00 AM', 'DateTime'],
            [$object, '2', $object, '2', __NAMESPACE__.'\ComparisonTest_Class'],
            '\DateInterval instance === \DateInterval instance' => [$dateInterval = new \DateInterval('P1W'), '7 days', $dateInterval, '7 days', \DateInterval::class],
            'negative \DateInterval instance === negative \DateInterval instance' => [$negativeDateInterval, '-2 years', $negativeDateInterval, '-2 years', \DateInterval::class],
        ];

        return $comparisons;
    }

    public function provideComparisonsToNullValueAtPropertyPath()
    {
        return [
            [5, '5', true],
        ];
    }
}
