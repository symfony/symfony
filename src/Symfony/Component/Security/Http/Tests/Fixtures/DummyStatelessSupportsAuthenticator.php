<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Fixtures;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\StatelessAuthenticatorInterface;

class DummyStatelessSupportsAuthenticator extends DummyAuthenticator implements StatelessAuthenticatorInterface
{
    private ?bool $supports;

    public function __construct(?bool $supports)
    {
        $this->supports = $supports;
    }

    public function supports(Request $request): ?bool
    {
        return $this->supports;
    }
}
