<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Asset\Context;

/**
 * A context that does nothing.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class NullContext implements ContextInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBasePath()
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function isSecure()
    {
        return false;
    }
}
