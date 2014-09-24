<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The SecurityContextInterface.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @deprecated Deprecated since version 2.6, to be removed in 3.0.
 */
interface SecurityContextInterface extends TokenStorageInterface, AuthorizationCheckerInterface, SecuritySessionStorageInterface
{
}
