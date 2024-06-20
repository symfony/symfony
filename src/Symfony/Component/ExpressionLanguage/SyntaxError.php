<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage;

class SyntaxError extends \LogicException
{
    public function __construct(string $message, int $cursor = 0, string $expression = '', ?string $subject = null, ?array $proposals = null)
    {
        $message = \sprintf('%s around position %d', rtrim($message, '.'), $cursor);
        if ($expression) {
            $message = \sprintf('%s for expression `%s`', $message, $expression);
        }
        $message .= '.';

        if (null !== $subject && null !== $proposals) {
            $minScore = \INF;
            foreach ($proposals as $proposal) {
                $distance = levenshtein($subject, $proposal);
                if ($distance < $minScore) {
                    $guess = $proposal;
                    $minScore = $distance;
                }
            }

            if (isset($guess) && $minScore < 3) {
                $message .= \sprintf(' Did you mean "%s"?', $guess);
            }
        }

        parent::__construct($message);
    }
}
