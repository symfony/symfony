<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Event;

use Symfony\Component\Workflow\Exception\InvalidArgumentException;

/**
 * @author Nicolas Rigaud <squrious@protonmail.com>
 *
 * @internal
 */
trait EventNameTrait
{
    /**
     * Gets the event name for workflow and transition.
     *
     * @throws InvalidArgumentException If $transitionName is provided without $workflowName
     */
    private static function getNameForTransition(?string $workflowName, ?string $transitionName): string
    {
        return self::computeName($workflowName, $transitionName);
    }

    /**
     * Gets the event name for workflow and place.
     *
     * @throws InvalidArgumentException If $placeName is provided without $workflowName
     */
    private static function getNameForPlace(?string $workflowName, ?string $placeName): string
    {
        return self::computeName($workflowName, $placeName);
    }

    private static function computeName(?string $workflowName, ?string $transitionOrPlaceName): string
    {
        $eventName = strtolower(basename(str_replace('\\', '/', static::class), 'Event'));

        if (null === $workflowName) {
            if (null !== $transitionOrPlaceName) {
                throw new \InvalidArgumentException('Missing workflow name.');
            }

            return \sprintf('workflow.%s', $eventName);
        }

        if (null === $transitionOrPlaceName) {
            return \sprintf('workflow.%s.%s', $workflowName, $eventName);
        }

        return \sprintf('workflow.%s.%s.%s', $workflowName, $eventName, $transitionOrPlaceName);
    }
}
