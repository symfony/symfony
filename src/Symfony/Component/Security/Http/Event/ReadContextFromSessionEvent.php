<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Core\SecurityContextInterface;

class ReadContextFromSessionEvent extends Event
{
    private $request;

    private $securityContext;

    public function __construct(Request $request, SecurityContextInterface $securityContext)
    {
        $this->request = $request;
        $this->securityContext = $securityContext;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getSecurityContext()
    {
        return $this->securityContext;
    }
}
