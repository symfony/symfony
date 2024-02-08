<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/my/route', name: 'my_route')]
final class MyController
{
    public function __invoke(): Response
    {
        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
