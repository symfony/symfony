<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Event;

use Symfony\Component\Tui\Event\AbstractEvent;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Dispatched when the text of a live region (aria-live, role="status" or
 * role="alert") changes.
 *
 * Terminals have no accessibility API: screen readers read the screen, and
 * miss what changes away from the cursor. Listen to this event, dispatched by a RichTextWidget created with a politeness, to keep a
 * persistent log of announcements, ring the bell for assertive ones, or
 * speak them.
 *
 * @experimental
 */
class LiveRegionChangedEvent extends AbstractEvent
{
    /**
     * @param 'polite'|'assertive' $politeness
     */
    public function __construct(AbstractWidget $target, private readonly string $previousText, private readonly string $text, private readonly string $politeness = 'polite')
    {
        parent::__construct($target);
    }

    public function getPreviousText(): string
    {
        return $this->previousText;
    }

    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @return 'polite'|'assertive'
     */
    public function getPoliteness(): string
    {
        return $this->politeness;
    }

    public function isAssertive(): bool
    {
        return 'assertive' === $this->politeness;
    }
}
