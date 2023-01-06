<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers\SubNamespace\EvenDeeperNamespace;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/my/other/route', name: 'my_other_controller_', methods: ['PUT'])]
final class MyOtherController
{
    #[Route('/first', name: 'one')]
    public function firstAction(): Response
    {
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/second', name: 'two')]
    public function secondAction(): Response
    {
        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
