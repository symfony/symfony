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
interface PropertyAccessorBuilderInterface
{
    /**
     * Enable the use of "__call" by the ProperyAccessor.
     *
     * @return PropertyAccessorBuilderInterface The builder object.
     */
    public function enableMagicCall();

    /**
     * Disable the use of "__call" by the ProperyAccessor.
     *
     * @return PropertyAccessorBuilderInterface The builder object.
     */
    public function disableMagicCall();

    /**
     * @return Boolean true if the use of "__call" by the ProperyAccessor is enable.
     */
    public function isMagicCallEnabled();

    /**
     * Builds and returns a new propertyAccessor object.
     *
     * @return PropertyAccessorInterface The built propertyAccessor.
     */
    public function getPropertyAccessor();
}
