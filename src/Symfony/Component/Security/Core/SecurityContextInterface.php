<?php

namespace Symfony\Component\Security\Core;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The SecurityContextInterface.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface SecurityContextInterface
{
    const ACCESS_DENIED_ERROR  = '_security.403_error';
    const AUTHENTICATION_ERROR = '_security.last_error';
    const LAST_USERNAME        = '_security.last_username';

    function getToken();
    function setToken(TokenInterface $token);
    function isGranted($attributes, $object = null);
}