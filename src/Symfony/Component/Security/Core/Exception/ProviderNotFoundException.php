<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

/**
 * ProviderNotFoundException is thrown when no AuthenticationProviderInterface instance
 * supports an authentication Token.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ProviderNotFoundException extends AuthenticationException
{
}
