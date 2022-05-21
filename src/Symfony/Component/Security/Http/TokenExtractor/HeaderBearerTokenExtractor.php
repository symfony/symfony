<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\TokenExtractor;

use Symfony\Component\HttpFoundation\Request;

/**
 * Implementation of the RFC 6750 for token extraction from Form-Encoded Body Parameter.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6750#section-2.1
 *
 * @author Vincent Chalamon <vincentchalamon@gmail.com>
 *
 * @final
 */
class HeaderBearerTokenExtractor implements BearerTokenExtractorInterface
{
    public function supports(Request $request): bool
    {
        return $request->headers->has('AUTHORIZATION') && str_starts_with($request->headers->get('AUTHORIZATION'), 'Bearer ');
    }

    public function extract(Request $request): string
    {
        return substr($request->headers->get('AUTHORIZATION'), 7);
    }
}
