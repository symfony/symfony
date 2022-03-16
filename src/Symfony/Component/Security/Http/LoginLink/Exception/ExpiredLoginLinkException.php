<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\LoginLink\Exception;

use Symfony\Component\Security\Core\Signature\Exception\ExpiredSignatureException;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class ExpiredLoginLinkException extends ExpiredSignatureException implements InvalidLoginLinkExceptionInterface
{
}
