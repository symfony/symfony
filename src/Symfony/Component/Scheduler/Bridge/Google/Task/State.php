<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Google\Task;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @see https://cloud.google.com/scheduler/docs/reference/rest/v1/projects.locations.jobs#State
 */
final class State
{
    public const STATE_UNSPECIFIED = 'STATE_UNSPECIFIED';
    public const ENABLED = 'ENABLED';
    public const PAUSED = 'PAUSED';
    public const DISABLED = 'DISABLED';
    public const UPDATE_FAILED = 'UPDATE_FAILED';
}
