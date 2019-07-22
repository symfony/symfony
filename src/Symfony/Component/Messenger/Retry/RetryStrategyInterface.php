<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Retry;

use Symfony\Component\Messenger\Envelope;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
interface RetryStrategyInterface
{
    public function isRetryable(Envelope $message): bool;

    /**
     * @return int The time to delay/wait in milliseconds
     */
    public function getWaitingTime(Envelope $message): int;
}
