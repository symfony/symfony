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
 * The default implementation of {@link PropertyAccessorBuilderInterface}.
 *
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class PropertyAccessorBuilder implements PropertyAccessorBuilderInterface
{
    /**
     * @var Boolean
     */
    private $magicCall = false;

    /**
     * {@inheritdoc}
     */
    public function enableMagicCall()
    {
        $this->magicCall = true;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function disableMagicCall()
    {
        $this->magicCall = false;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isMagicCallEnabled()
    {
        return $this->magicCall;
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyAccessor()
    {
        return new PropertyAccessor($this->magicCall);
    }
}
