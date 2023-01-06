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

final class MyUnannotatedController
{
    public function myAction(): Response
    {
        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
