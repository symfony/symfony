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
 * Dispatched when a link is followed with the keyboard, like Enter on a focused <a href>.
 *
 * An HTML view goes to the links to a fragment of the page (#id); what
 * other links do is up to the application, a terminal opens none: stop the
 * propagation of the event to keep the view from going to a fragment.
 *
 * @experimental
 */
class LinkEvent extends AbstractEvent
{
    public function __construct(
        AbstractWidget $target,
        private readonly string $href,
    ) {
        parent::__construct($target);
    }

    /**
     * The href of the link, as written.
     */
    public function getHref(): string
    {
        return $this->href;
    }
}
