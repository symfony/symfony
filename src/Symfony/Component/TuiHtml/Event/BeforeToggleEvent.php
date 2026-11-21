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

/**
 * Dispatched on a popover or a dialog before it is shown or hidden, like the beforetoggle event of HTML: stopping
 * it keeps it from being shown; hiding it cannot be stopped.
 *
 * @experimental
 */
class BeforeToggleEvent extends ToggleEvent
{
}
