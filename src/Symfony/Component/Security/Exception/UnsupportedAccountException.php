<?php

namespace Symfony\Component\Security\Exception;

/**
 * This exception is thrown when an account is reloaded from a provider which
 * doesn't support the passed implementation of AccountInterface.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class UnsupportedAccountException extends AuthenticationServiceException
{
}