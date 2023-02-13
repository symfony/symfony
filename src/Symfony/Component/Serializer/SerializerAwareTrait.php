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
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
trait SerializerAwareTrait
{
    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @return void
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }
}
