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
final class MonthsExpressionValidator implements ExpressionValidatorInterface
{
    public const EXPRESSION = '#\*|\/?\d|1[0-2-|,]|[1-9-|,]#';

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
        return 3;
    }
}
