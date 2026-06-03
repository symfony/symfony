<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Scheduler\Generator\MessageContext;

final class ScheduledStamp implements StampInterface
{
    public function __construct(public readonly MessageContext $messageContext)
    {
    }
}
