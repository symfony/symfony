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
     * @var Boolean
     */
    private $magicCall = false;

    /**
     * @var Boolean
     */
    private $throwExceptionOnInvalidIndex = false;

    /**
     * Enables the use of "__call" by the PropertyAccessor.
     *
     * @return PropertyAccessorBuilder The builder object
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
     */
    public function disableMagicCall()
    {
        $this->magicCall = false;

        return $this;
    }

    /**
     * @return Boolean true if the use of "__call" by the PropertyAccessor is enabled
     */
    public function isMagicCallEnabled()
    {
        return $this->magicCall;
    }

    /**
     * Enables exceptions in read context for array by PropertyAccessor
     *
     * @return PropertyAccessorBuilder The builder object
     */
    public function enableExceptionOnInvalidIndex()
    {
        $this->throwExceptionOnInvalidIndex = true;

        return $this;
    }

    /**
     * Disables exceptions in read context for array by PropertyAccessor
     *
     * @return PropertyAccessorBuilder The builder object
     */
    public function disableExceptionOnInvalidIndex()
    {
        $this->throwExceptionOnInvalidIndex = false;

        return $this;
    }

    /**
     * @return Boolean true is exceptions in read context for array is enabled
     */
    public function isExceptionOnInvalidIndexEnabled()
    {
        return $this->throwExceptionOnInvalidIndex;
    }

    /**
     * Builds and returns a new propertyAccessor object.
     *
     * @return PropertyAccessorInterface The built propertyAccessor
     */
    public function getPropertyAccessor()
    {
        return new PropertyAccessor($this->magicCall, $this->throwExceptionOnInvalidIndex);
    }
}
