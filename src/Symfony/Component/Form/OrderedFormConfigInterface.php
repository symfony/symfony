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
 * @author GeLo <geloen.eric@gmail.com>
 */
interface OrderedFormConfigInterface
{
    /**
     * Gets the form position.
     *
     * @see FormConfigBuilderInterface::setPosition
     *
     * @return null|string|array The position.
     */
    public function getPosition();
}
