<?php

namespace Symfony\Component\Security\Guard\Token;

/**
 * A marker interface that both guard tokens implement.
 *
 * Any tokens passed to GuardAuthenticationProvider (i.e. any tokens that
 * are handled by the guard auth system) must implement this
 * interface.
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 */
interface GuardTokenInterface
{
}
