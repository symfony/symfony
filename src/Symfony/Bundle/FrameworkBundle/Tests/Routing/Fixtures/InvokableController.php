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

#[Route(path: '/here', name: 'lol', methods: ['GET', 'POST'], schemes: ['https'])]
class InvokableController
{
    public function __invoke()
    {
    }
}
