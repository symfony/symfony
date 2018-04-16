<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization;

use Symfony\Component\Messenger\EnvelopeItemInterface;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @experimental in 4.1
 */
final class SerializerConfiguration implements EnvelopeItemInterface
{
    private $context;

    public function __construct(array $context)
    {
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function serialize()
    {
        return serialize(array('context' => $this->context));
    }

    public function unserialize($serialized)
    {
        list('context' => $context) = unserialize($serialized, array('allowed_classes' => false));

        $this->__construct($context);
    }
}
