<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Serializer\SerializerInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * @author Jesse Rushlow <jr@rushlow.dev>
 */
final class SerializerRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private SerializerInterface $serializer,
    ) {
    }

    public function serialize(mixed $data, string $format = 'json', array $context = []): string
    {
        return $this->serializer->serialize($data, $format, $context);
    }
}
