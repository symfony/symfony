<?php

namespace Symfony\Component\Serializer;

use Symfony\Component\Serializer\SerializerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Defines the interface of encoders
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface SerializerAwareInterface
{
    /**
     * Sets the owning Serializer object
     *
     * @param SerializerInterface $serializer
     */
    function setSerializer(SerializerInterface $serializer);
}
