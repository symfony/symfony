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
use Symfony\Component\Validator\Validation;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class NotIdenticalToValidatorTest extends AbstractComparisonValidatorTestCase
{
    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_5;
    }

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
            array(new \DateTime('2001-01-01'), '2000-01-01'),
            array(new \DateTime('2000-01-01'), '2000-01-01'),
            array(new \DateTime('2001-01-01'), '2000-01-01'),
            array(new \DateTime('2000-01-01 UTC'), '2000-01-01 UTC'),
            array(null, 1),
        );
    }

    public function provideAllInvalidComparisons()
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
    public function provideInvalidComparisons()
    {
        $date = new \DateTime('2000-01-01');
        $object = new ComparisonTest_Class(2);

        $comparisons = array(
            array(3, '3', 3, '3', 'integer'),
            array('a', '"a"', 'a', '"a"', 'string'),
            array($date, 'Jan 1, 2000, 12:00 AM', $date, 'Jan 1, 2000, 12:00 AM', 'DateTime'),
            array($object, '2', $object, '2', __NAMESPACE__.'\ComparisonTest_Class'),
        );

<<<<<<< HEAD
        if (version_compare(PHP_VERSION, '>=', '5.5')) {
            $immutableDate = new \DateTimeImmutable('2000-01-01');
            $comparisons[] = array($immutableDate, 'Jan 1, 2000, 12:00 AM', $immutableDate, 'Jan 1, 2000, 12:00 AM', 'DateTime');
        }
=======
        $immutableDate = new \DateTimeImmutable('2000-01-01');
        $comparisons[] = array($immutableDate, 'Jan 1, 2000, 12:00 AM', $immutableDate, 'Jan 1, 2000, 12:00 AM', 'DateTime');
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

        return $comparisons;
    }
}
