<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * A clickable form element.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ClickableInterface
{
    /**
     * Returns whether this element was clicked.
     *
     * @return bool    Whether this element was clicked.
     */
    public function isClicked();
}
