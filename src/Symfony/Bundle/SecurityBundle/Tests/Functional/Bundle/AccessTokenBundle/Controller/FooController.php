<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\AccessTokenBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\User\UserInterface;

class FooController
{
    public function __invoke(UserInterface $user): JsonResponse
    {
        return new JsonResponse(['message' => sprintf('Welcome @%s!', $user->getUserIdentifier())]);
    }
}
