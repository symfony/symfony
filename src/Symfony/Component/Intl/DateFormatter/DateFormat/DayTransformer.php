<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\DateFormatter\DateFormat;

/**
 * Parser and formatter for day format.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 *
 * @internal
 */
class DayTransformer extends Transformer
{
    /**
     * {@inheritdoc}
     */
    public function format(\DateTime $dateTime, int $length): string
    {
        return $this->padLeft($dateTime->format('j'), $length);
    }

    /**
     * {@inheritdoc}
     */
    public function getReverseMatchingRegExp(int $length): string
    {
        return 1 === $length ? '\d{1,2}' : '\d{'.$length.'}';
    }

    /**
     * {@inheritdoc}
     */
    public function extractDateOptions(string $matched, int $length): array
    {
        return array(
            'day' => (int) $matched,
        );
    }
}
