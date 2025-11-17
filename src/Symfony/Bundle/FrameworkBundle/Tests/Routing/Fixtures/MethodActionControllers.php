<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Routing\Fixtures;

use Symfony\Component\Routing\Attribute\Route;

#[Route('/the/path')]
class MethodActionControllers
{
    #[Route(name: 'post', methods: ['POST'])]
    public function post()
    {
    }

    #[Route(name: 'put', methods: ['PUT'], priority: 10)]
    public function put()
    {
    }
}
