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
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * @since v2.0.0
 */
class SwitchUserEvent extends Event
{
    private $request;

    private $targetUser;

    /**
     * @since v2.0.0
     */
    public function __construct(Request $request, UserInterface $targetUser)
    {
        $this->request = $request;
        $this->targetUser = $targetUser;
    }

    /**
     * @since v2.0.0
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @since v2.0.0
     */
    public function getTargetUser()
    {
        return $this->targetUser;
    }
}
