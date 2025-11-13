<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Beanstalkd\Transport;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class BeanstalkdPriorityStamp implements StampInterface
{
    public function __construct(
        public readonly int $priority,
    ) {
    }
}
