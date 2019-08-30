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

    protected function createConstraint(array $options = null): Constraint
    {
        return new GreaterThanOrEqual($options);
    }

    protected function getErrorCode(): ?string
    {
        return GreaterThanOrEqual::TOO_LOW_ERROR;
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisons(): array
    {
        $negativeDateInterval = new \DateInterval('PT30S');
        $negativeDateInterval->invert = 1;

        return [
            [3, 2],
            [1, 1],
            [new \DateTime('2010/01/01'), new \DateTime('2000/01/01')],
            [new \DateTime('2000/01/01'), new \DateTime('2000/01/01')],
            [new \DateTime('2010/01/01'), '2000/01/01'],
            [new \DateTime('2000/01/01'), '2000/01/01'],
            [new \DateTime('2010/01/01 UTC'), '2000/01/01 UTC'],
            [new \DateTime('2000/01/01 UTC'), '2000/01/01 UTC'],
            ['a', 'a'],
            ['z', 'a'],
            [null, 1],
            ['30 > 29 (string)' => new \DateInterval('PT30S'), '+29 seconds'],
            ['30 > 29 (\DateInterval instance)' => new \DateInterval('PT30S'), new \DateInterval('PT29S')],
            ['30 = 30 (string)' => new \DateInterval('PT30S'), '+30 seconds'],
            ['30 = 30 (\DateInterval instance)' => new \DateInterval('PT30S'), new \DateInterval('PT30S')],
            ['-30 > -31' => $negativeDateInterval, '-31 seconds'],
            ['-30 = -30' => $negativeDateInterval, '-30 seconds'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [5],
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
            [new \DateTime('2000/01/01'), 'Jan 1, 2000, 12:00 AM', new \DateTime('2005/01/01'), 'Jan 1, 2005, 12:00 AM', 'DateTime'],
            [new \DateTime('2000/01/01'), 'Jan 1, 2000, 12:00 AM', '2005/01/01', 'Jan 1, 2005, 12:00 AM', 'DateTime'],
            [new \DateTime('2000/01/01 UTC'), 'Jan 1, 2000, 12:00 AM', '2005/01/01 UTC', 'Jan 1, 2005, 12:00 AM', 'DateTime'],
            ['b', '"b"', 'c', '"c"', 'string'],
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
