<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FirewallEntryPointBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class EntryPointStub implements AuthenticationEntryPointInterface
{
    const RESPONSE_TEXT = '2be8e651259189d841a19eecdf37e771e2431741';

    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new Response(self::RESPONSE_TEXT);
    }
}
