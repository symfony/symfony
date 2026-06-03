<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Context\Normalizer;

use Symfony\Component\Serializer\Context\ContextBuilderInterface;
use Symfony\Component\Serializer\Context\ContextBuilderTrait;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;

/**
 * A helper providing autocompletion for available DateIntervalNormalizer options.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class DateIntervalNormalizerContextBuilder implements ContextBuilderInterface
{
    use ContextBuilderTrait;

    /**
     * Configures the format of the interval.
     *
     * @see https://php.net/manual/en/dateinterval.format.php
     */
    public function withFormat(?string $format): static
    {
        return $this->with(DateIntervalNormalizer::FORMAT_KEY, $format);
    }
}
