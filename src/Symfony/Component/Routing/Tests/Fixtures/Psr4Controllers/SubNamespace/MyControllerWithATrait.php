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

use Symfony\Component\Routing\Attribute\Route;

#[Route('/my/controller/with/a/trait', name: 'my_controller_')]
final class MyControllerWithATrait implements IrrelevantInterface
{
    use SomeSharedImplementation;
}
