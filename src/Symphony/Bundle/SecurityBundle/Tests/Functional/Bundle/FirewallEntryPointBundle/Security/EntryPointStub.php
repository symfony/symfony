<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\Functional\Bundle\FirewallEntryPointBundle\Security;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class EntryPointStub implements AuthenticationEntryPointInterface
{
    const RESPONSE_TEXT = '2be8e651259189d841a19eecdf37e771e2431741';

    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new Response(self::RESPONSE_TEXT);
    }
}
