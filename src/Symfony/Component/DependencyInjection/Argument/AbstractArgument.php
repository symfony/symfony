<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

/**
 * Represents an abstract service argument, which have to be set by a compiler pass or a DI extension.
 */
final class AbstractArgument
{
    private $serviceId;
    private $argKey;
    private $text;

    public function __construct(string $serviceId, string $argKey, string $text = '')
    {
        $this->serviceId = $serviceId;
        $this->argKey = $argKey;
        $this->text = $text;
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public function getArgumentKey(): string
    {
        return $this->argKey;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
