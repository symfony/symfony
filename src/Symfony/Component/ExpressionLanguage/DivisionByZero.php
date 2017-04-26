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

class DivisionByZero extends \LogicException
{
    public function __construct()
    {
        parent::__construct('Division by zero exception');
    }
}
