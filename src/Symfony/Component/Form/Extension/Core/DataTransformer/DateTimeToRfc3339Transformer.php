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
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated The Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToRfc3339Transformer class is deprecated since version 4.1 and will be removed in 5.0. Use the Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeImmutableToRfc3339Transformer class instead.
 */
class DateTimeToRfc3339Transformer extends BaseDateTimeTransformer
{
    use DateTimeImmutableTransformerDecoratorTrait;

    public function __construct($inputTimezone = null, $outputTimezone = null)
    {
        parent::__construct($inputTimezone, $outputTimezone);
        $this->decorated = new DateTimeImmutableToRfc3339Transformer($inputTimezone, $outputTimezone);
    }
}
