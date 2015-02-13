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
    public function __construct($message, $cursor = 0)
    {
        parent::__construct(sprintf('%s around position %d.', $message, $cursor));
    }
}
