<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests;

use Symfony\Bridge\Monolog\Logger;

class ClassThatInheritLogger extends Logger
{
    public function getLogs(): array
    {
        return parent::getLogs();
    }

    public function countErrors(): int
    {
        return parent::countErrors();
    }
}
