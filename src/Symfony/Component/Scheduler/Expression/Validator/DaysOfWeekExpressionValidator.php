<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Expression\Validator;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class DaysOfWeekExpressionValidator implements ExpressionValidatorInterface
{
    private const EXPRESSION = '#\*|[0-6-|,]#';

    /**
     * {@inheritdoc}
     */
    public function isValid(string $expression): bool
    {
        return preg_match(self::EXPRESSION, $expression);
    }

    /**
     * {@inheritdoc}
     */
    public function getPosition(): int
    {
        return 4;
    }
}
