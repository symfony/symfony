<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\AccountInterface;
use Doctrine\Common\EventArgs;

class SwitchUserEventArgs extends EventArgs
{
    private $request;

    private $targetUser;

    public function __construct(Request $request, AccountInterface $targetUser)
    {
        $this->request = $request;
        $this->targetUser = $targetUser;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getTargetUser()
    {
        return $this->targetUser;
    }
}