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
 *
 * @since v2.3.0
 */
class PropertyAccessorBuilder
{
    /**
     * @var Boolean
     */
    private $magicCall = false;

    /**
     * Enables the use of "__call" by the PropertyAccessor.
     *
     * @return PropertyAccessorBuilder The builder object
     *
     * @since v2.3.0
     */
    public function enableMagicCall()
    {
        $this->magicCall = true;

        return $this;
    }

    /**
     * Disables the use of "__call" by the PropertyAccessor.
     *
     * @return PropertyAccessorBuilder The builder object
     *
     * @since v2.3.0
     */
    public function disableMagicCall()
    {
        $this->magicCall = false;

        return $this;
    }

    /**
     * @return Boolean true if the use of "__call" by the ProperyAccessor is enabled
     *
     * @since v2.3.0
     */
    public function isMagicCallEnabled()
    {
        return $this->magicCall;
    }

    /**
     * Builds and returns a new propertyAccessor object.
     *
     * @return PropertyAccessorInterface The built propertyAccessor
     *
     * @since v2.3.0
     */
    public function getPropertyAccessor()
    {
        return new PropertyAccessor($this->magicCall);
    }
}
