<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Debug;

interface WrappingListenerInterface
{
    /**
     * Returns wrapped listener.
     *
     * @return callable
     */
    public function getWrappedListener();
}
