<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

/**
 * A widget that renders its entire allocated box, including layout and chrome.
 *
 * The renderer passes full allocated dimensions and the resolved style to render().
 * The widget owns padding, borders, background and any rendering of its children;
 * the renderer does not apply them again or traverse container children for it.
 * Normal lifecycle hooks, caching and output validation still apply.
 *
 * @experimental
 */
interface SelfRenderingInterface
{
}
