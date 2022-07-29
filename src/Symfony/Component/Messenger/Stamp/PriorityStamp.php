<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;

/**
 * Apply this stamp to provide priority for your message on a transport.
 *
 * @author Valentin Nazarov <i.kozlice@protonmail.com>
 */
final class PriorityStamp implements StampInterface
{
    public const MIN_PRIORITY = 0;
    public const MAX_PRIORITY = 255;

    private int $priority;

    /**
     * @param int $priority The priority level
     */
    public function __construct(int $priority)
    {
        if ($priority < self::MIN_PRIORITY || $priority > self::MAX_PRIORITY) {
            throw new InvalidArgumentException(sprintf('Priority must be between %d and %d.', self::MIN_PRIORITY, self::MAX_PRIORITY));
        }

        $this->priority = $priority;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}
