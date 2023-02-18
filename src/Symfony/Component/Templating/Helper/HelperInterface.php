<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Helper;

/**
 * HelperInterface is the interface all helpers must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface HelperInterface
{
    /**
     * Returns the canonical name of this helper.
     *
     * @return string
     */
    public function getName();

    /**
     * Sets the default charset.
     *
     * @return void
     */
    public function setCharset(string $charset);

    /**
     * Gets the default charset.
     */
    public function getCharset(): string;
}
