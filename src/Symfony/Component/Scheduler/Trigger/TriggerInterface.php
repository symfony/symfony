<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Trigger;

interface TriggerInterface extends \Stringable
{
    /**
     * Returns the next run date; if null is returned, this method won't be called again.
     */
    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable;
}
