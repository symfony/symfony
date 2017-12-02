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
 * Transforms between a normalized time and a localized time string/array.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @deprecated The Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer class is deprecated since version 4.1 and will be removed in 5.0. Use the Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeImmutableToArrayTransformer class instead.
 */
class DateTimeToArrayTransformer extends BaseDateTimeTransformer
{
    use DateTimeImmutableTransformerDecoratorTrait;

    /**
     * @param string $inputTimezone  The input timezone
     * @param string $outputTimezone The output timezone
     * @param array  $fields         The date fields
     * @param bool   $pad            Whether to use padding
     */
    public function __construct(string $inputTimezone = null, string $outputTimezone = null, array $fields = null, bool $pad = false)
    {
        parent::__construct($inputTimezone, $outputTimezone);
        $this->decorated = new DateTimeImmutableToArrayTransformer($inputTimezone, $outputTimezone, $fields, $pad);
    }
}
