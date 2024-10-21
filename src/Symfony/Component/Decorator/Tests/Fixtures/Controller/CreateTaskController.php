<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Decorator\Tests\Fixtures\Controller;

use Symfony\Component\Decorator\Tests\Fixtures\Decorator\Json;
use Symfony\Component\Decorator\Tests\Fixtures\Decorator\Logging;

final readonly class CreateTaskController
{
    #[Logging]
    #[Json]
    public function __invoke(): Task
    {
        return new Task(1, 'Take a break!');
    }
}
