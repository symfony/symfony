<?php

namespace Symfony\Component\Security\Core\Tests\Authentication\Token\Fixtures;

use Symfony\Component\Security\Core\User\UserInterface;

class UserWithPredefinedEraseCredentialMethod implements UserInterface
{
    use UserHavingPlaintextCredentialTrait;
}
