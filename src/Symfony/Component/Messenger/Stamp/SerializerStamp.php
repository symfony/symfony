<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class SerializerStamp implements StampInterface
{
    private array $context;

    public function __construct(array $context)
    {
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
