<?php

namespace Symfony\Component\Security\Core\Tests\Authentication\Token\Fixtures;

use Symfony\Component\Security\Core\User\PlaintextCredentialUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserWithPlaintextCredentials implements UserInterface, PlaintextCredentialUserInterface
{
    use UserHavingPlaintextCredentialTrait;
}
