<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\Functional\Bundle\JsonLoginBundle\Controller;

use Symphony\Component\HttpFoundation\JsonResponse;
use Symphony\Component\Security\Core\User\UserInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class TestController
{
    public function loginCheckAction(UserInterface $user)
    {
        return new JsonResponse(array('message' => sprintf('Welcome @%s!', $user->getUsername())));
    }
}
