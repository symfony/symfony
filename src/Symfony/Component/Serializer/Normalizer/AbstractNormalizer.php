<?php

namespace Symfony\Component\Serializer\Normalizer;

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
 * Abstract Normalizer implementation
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
abstract class AbstractNormalizer implements NormalizerInterface
{
    protected $serializer;

    /**
     * {@inheritdoc}
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializer()
    {
        return $this->serializer;
    }
}