<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Encoder;

use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;

/**
 * SerializerAware Encoder implementation
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @since v2.0.0
 */
abstract class SerializerAwareEncoder implements SerializerAwareInterface
{
    protected $serializer;

    /**
     * {@inheritdoc}
     *
     * @since v2.0.0
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }
}
