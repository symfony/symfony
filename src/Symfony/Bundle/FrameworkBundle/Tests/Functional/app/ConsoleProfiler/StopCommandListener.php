<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConsoleProfiler;

use Symfony\Component\Console\Event\ConsoleCommandEvent;

class StopCommandListener
{
    /** @var callable|null */
    public $listener;

    public function __invoke(ConsoleCommandEvent $event): void
    {
        ($this->listener ?? static fn () => null)($event);
    }
}
