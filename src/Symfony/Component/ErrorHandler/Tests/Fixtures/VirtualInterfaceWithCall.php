<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

/**
 * @method string magicInterfaceMethod()
 */
interface VirtualInterfaceWithCall
{
    public function __call(string $name, array $arguments): mixed;
}
