<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Processor;

use Symfony\Bridge\Monolog\Processor\DebugProcessor;

class ClassThatInheritDebugProcessor extends DebugProcessor
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
