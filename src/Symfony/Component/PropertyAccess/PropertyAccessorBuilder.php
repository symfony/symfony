<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess;

/**
 * A configurable builder for PropertyAccessorInterface objects.
 *
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class PropertyAccessorBuilder
{
    /**
     * @var bool
     */
    private $magicCall = false;

    /**
     * Enables the use of "__call" by the ProperyAccessor.
     *
     * @return PropertyAccessorBuilder The builder object
     */
    public function enableMagicCall()
    {
        $this->magicCall = true;

        return $this;
    }

    /**
     * Disables the use of "__call" by the ProperyAccessor.
     *
     * @return PropertyAccessorBuilder The builder object
     */
    public function disableMagicCall()
    {
        $this->magicCall = false;

        return $this;
    }

    /**
     * @return bool    true if the use of "__call" by the ProperyAccessor is enabled
     */
    public function isMagicCallEnabled()
    {
        return $this->magicCall;
    }

    /**
     * Builds and returns a new propertyAccessor object.
     *
     * @return PropertyAccessorInterface The built propertyAccessor
     */
    public function getPropertyAccessor()
    {
        return new PropertyAccessor($this->magicCall);
    }
}
