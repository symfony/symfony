<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\Loop;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class LoopEvents
{
    const RUN = 'worker.run';
    const HEALTH_CHECK = 'worker.health_check';
    const WAKE_UP = 'worker.wake_up';
    const SLEEP = 'worker.sleep';
    const STOP = 'worker.stop';

    private function __construct()
    {
    }
}
