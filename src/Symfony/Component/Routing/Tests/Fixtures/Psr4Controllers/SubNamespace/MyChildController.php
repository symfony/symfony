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

use Symfony\Component\Routing\Annotation\Route;

#[Route('/my/child/controller', name: 'my_child_controller_')]
final class MyChildController extends MyAbstractController
{
}
