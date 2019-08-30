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

    protected function createConstraint(array $options = null): Constraint
    {
        return new GreaterThan($options);
    }

    protected function getErrorCode(): ?string
    {
        return GreaterThan::TOO_LOW_ERROR;
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisons(): array
    {
        $negativeDateInterval = new \DateInterval('PT30S');
        $negativeDateInterval->invert = 1;

        return [
            [2, 1],
            [new \DateTime('2005/01/01'), new \DateTime('2001/01/01')],
            [new \DateTime('2005/01/01'), '2001/01/01'],
            [new \DateTime('2005/01/01 UTC'), '2001/01/01 UTC'],
            [new ComparisonTest_Class(5), new ComparisonTest_Class(4)],
            ['333', '22'],
            [null, 1],
            ['30 > 29 (string)' => new \DateInterval('PT30S'), '+29 seconds'],
            ['30 > 29 (\DateInterval instance)' => new \DateInterval('PT30S'), new \DateInterval('PT29S')],
            ['-30 > -31' => $negativeDateInterval, '-31 seconds'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [6],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function provideInvalidComparisons(): array
    {
        $negativeDateInterval = new \DateInterval('PT30S');
        $negativeDateInterval->invert = 1;

        return [
            [1, '1', 2, '2', 'integer'],
            [2, '2', 2, '2', 'integer'],
            [new \DateTime('2000/01/01'), 'Jan 1, 2000, 12:00 AM', new \DateTime('2005/01/01'), 'Jan 1, 2005, 12:00 AM', 'DateTime'],
            [new \DateTime('2000/01/01'), 'Jan 1, 2000, 12:00 AM', new \DateTime('2000/01/01'), 'Jan 1, 2000, 12:00 AM', 'DateTime'],
            [new \DateTime('2000/01/01'), 'Jan 1, 2000, 12:00 AM', '2005/01/01', 'Jan 1, 2005, 12:00 AM', 'DateTime'],
            [new \DateTime('2000/01/01'), 'Jan 1, 2000, 12:00 AM', '2000/01/01', 'Jan 1, 2000, 12:00 AM', 'DateTime'],
            [new \DateTime('2000/01/01 UTC'), 'Jan 1, 2000, 12:00 AM', '2005/01/01 UTC', 'Jan 1, 2005, 12:00 AM', 'DateTime'],
            [new \DateTime('2000/01/01 UTC'), 'Jan 1, 2000, 12:00 AM', '2000/01/01 UTC', 'Jan 1, 2000, 12:00 AM', 'DateTime'],
            [new ComparisonTest_Class(4), '4', new ComparisonTest_Class(5), '5', __NAMESPACE__.'\ComparisonTest_Class'],
            [new ComparisonTest_Class(5), '5', new ComparisonTest_Class(5), '5', __NAMESPACE__.'\ComparisonTest_Class'],
            ['22', '"22"', '333', '"333"', 'string'],
            ['22', '"22"', '22', '"22"', 'string'],
            ['30 < 31 (string)' => new \DateInterval('PT30S'), '30 seconds', '+31 seconds', '31 seconds', \DateInterval::class],
            ['30 < 31 (\DateInterval instance)' => new \DateInterval('PT30S'), '30 seconds', new \DateInterval('PT31S'), '31 seconds', \DateInterval::class],
            ['-30 < -29' => $negativeDateInterval, '-30 seconds', '-29 seconds', '-29 seconds', \DateInterval::class],
        ];
    }

    public function provideComparisonsToNullValueAtPropertyPath()
    {
        return [
            [5, '5', true],
        ];
    }
}
