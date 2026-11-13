<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Css;

/**
 * Loads the stylesheets referenced by <link rel="stylesheet"> and @import.
 *
 * @experimental
 */
interface StylesheetLoaderInterface
{
    /**
     * @param string $href The URL as written, like "styles/app.css"
     *
     * @return string The CSS source
     *
     * @throws \Symfony\Component\Tui\Exception\InvalidArgumentException when the stylesheet cannot be found
     */
    public function load(string $href): string;
}
