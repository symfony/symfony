<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

/**
 * Interface used for marking tokens that do not represent the currently logged-in user.
 *
 * @author Nate Wiebe <nate@northern.co>
 */
interface OfflineTokenInterface extends TokenInterface
{
}
