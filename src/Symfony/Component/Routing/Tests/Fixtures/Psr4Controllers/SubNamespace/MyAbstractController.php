<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures\Psr4Controllers\SubNamespace;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

abstract class MyAbstractController
{
    #[Route('/a/route/from/an/abstract/controller', name: 'from_abstract')]
    public function someAction(): Response
    {
        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
