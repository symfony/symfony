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
 * A widget made of links, like text with links or an image map: focused, one of them is current, Enter following it.
 *
 * It is not focused first when a page is shown, nor activated by a click, the terminal following the link clicked.
 *
 * @experimental
 */
interface LinksInterface
{
    /**
     * The href of the current link, null when it has not the focus.
     */
    public function getCurrentHref(): ?string;
}
