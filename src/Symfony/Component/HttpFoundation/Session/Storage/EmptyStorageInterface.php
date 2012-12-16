<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage;

/**
 * EmptyStorageInterface.
 *
 * @author Terje Bråten <terje@braten.be>
 */
interface EmptyStorageInterface extends SessionStorageInterface
{
    /**
     * Used by the empty bag to signal that we now need to get the real bag
     * when the time is right (somthing has been written to the bag)
     */
    public function getRealBag($name);
}
