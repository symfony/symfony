<?php

namespace Symfony\Component\Security\Guard\Tests\Fixtures;

use Symfony\Component\Security\Guard\Token\GuardTokenInterface as BaseGuardTokenInterface;

interface GuardTokenInterface extends BaseGuardTokenInterface
{
    public function __serialize(): array;
    public function __unserialize(array $data): void;
}
