<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Generator;

use Symfony\Component\Scheduler\RecurringMessage;

/**
 * @experimental
 */
interface MessageGeneratorInterface
{
    /**
     * @return iterable<object|RecurringMessage>
     */
    public function getMessages(): iterable;
}
