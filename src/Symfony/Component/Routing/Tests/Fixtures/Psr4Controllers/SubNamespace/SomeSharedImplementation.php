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

trait SomeSharedImplementation
{
    #[Route('/a/route/from/a/trait', name: 'with_a_trait')]
    public function someAction(): Response
    {
        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
