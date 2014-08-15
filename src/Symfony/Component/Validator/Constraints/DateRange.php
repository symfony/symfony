<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Class DateRange
 *
 * @Annotation
 * @Target({"CLASS"})
 *
 * @author Bez Hermoso <bezalelhermoso@gmail.com>
 */
class DateRange extends Constraint
{
    /**
     * @var string The property which contains the start date.
     */
    public $start;

    /**
     * @var string The property which contains the end date.
     */
    public $end;

    public $startMessage = 'Start date should be less than or equal to {{ limit }}';

    public $endMessage = 'End date should be greater than or equal to {{ limit }}';

    public $emptyStartMessage = 'Start date cannot be empty';

    public $emptyEndMessage = 'End date cannot be empty';

    public $invalidMessage = 'Invalid date range';

    public $limitFormat = 'Y-m-d';

    /**
     * @var string The property to attach the error message on.
     */
    public $errorPath = false;

    public function getRequiredOptions()
    {
        return array(
            'start',
            'end',
        );
    }

    public function getTargets()
    {
        return static::CLASS_CONSTRAINT;
    }
}
