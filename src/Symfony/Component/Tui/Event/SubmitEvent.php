<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Event;

use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Event dispatched when content is submitted (e.g., Enter pressed in Input/Editor).
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SubmitEvent extends AbstractEvent
{
    public function __construct(
        AbstractWidget $target,
        private readonly string $value,
    ) {
        parent::__construct($target);
    }

    /**
     * Get the submitted value.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Check if the submitted value is empty or contains only whitespace.
     */
    public function isBlank(): bool
    {
        return '' === trim($this->value);
    }
}
