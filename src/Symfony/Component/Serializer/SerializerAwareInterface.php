<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer;

/**
 * @deprecated since Symfony 7.2, use {@see NormalizerAwareInterface} or {@see DenormalizerAwareInterface} instead
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface SerializerAwareInterface
{
    /**
     * Sets the owning Serializer object.
     *
     * @deprecated since Symfony 7.2, use {@see NormalizerAwareInterface} or {@see DenormalizerAwareInterface} instead
     */
    public function setSerializer(SerializerInterface $serializer): void;
}
