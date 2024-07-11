<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authorization;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * This is a basic NotFullFledgedHandle
 * If IS_AUTHENTICATED_FULLY is in access denied Exception Attrribute, behavior will be as before,
 * Otherwise The original AccessDeniedException is throw
 *
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
class SameAsNotFullFledgedHandle implements NotFullFledgedHandlerInterface
{
    public function handle(Request $request, AccessDeniedException $accessDeniedException, AuthenticationTrustResolverInterface $trustResolver, ?TokenInterface $token, callable $reauthenticateResponse): ?Response
    {
        if( !$trustResolver->isAuthenticated($token)) {
            $reauthenticateResponse();
        }

        foreach($accessDeniedException->getAttributes() as $attribute) {
            if(in_array($attribute, [AuthenticatedVoter::IS_AUTHENTICATED_FULLY])) {
                $reauthenticateResponse();
            }
        }
        return null;
    }
}
