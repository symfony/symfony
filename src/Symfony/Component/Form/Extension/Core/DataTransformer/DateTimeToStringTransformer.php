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
 * Transforms between a date string and a DateTime object.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @deprecated The Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer class is deprecated since version 4.1 and will be removed in 5.0. Use the Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeImmutableToStringTransformer class instead.
 */
class DateTimeToStringTransformer extends BaseDateTimeTransformer
{
    use DateTimeImmutableTransformerDecoratorTrait;

    /**
     * Transforms a \DateTime instance to a string.
     *
     * @see \DateTime::format() for supported formats
     *
     * @param string $inputTimezone  The name of the input timezone
     * @param string $outputTimezone The name of the output timezone
     * @param string $format         The date format
     */
    public function __construct(string $inputTimezone = null, string $outputTimezone = null, string $format = 'Y-m-d H:i:s')
    {
        parent::__construct($inputTimezone, $outputTimezone);
        $this->decorated = new DateTimeImmutableToStringTransformer($inputTimezone, $outputTimezone, $format);
    }
}
