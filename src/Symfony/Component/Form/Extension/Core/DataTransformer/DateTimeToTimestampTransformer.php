<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

/**
 * Transforms between a timestamp and a DateTime object.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @deprecated The Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToTimestampTransformer class is deprecated since version 4.1 and will be removed in 5.0. Use the Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeImmutableToTimestampTransformer class instead.
 */
class DateTimeToTimestampTransformer extends BaseDateTimeTransformer
{
    use DateTimeImmutableTransformerDecoratorTrait;

    public function __construct($inputTimezone = null, $outputTimezone = null)
    {
        parent::__construct($inputTimezone, $outputTimezone);
        $this->decorated = new DateTimeImmutableToTimestampTransformer($inputTimezone, $outputTimezone);
    }
}
