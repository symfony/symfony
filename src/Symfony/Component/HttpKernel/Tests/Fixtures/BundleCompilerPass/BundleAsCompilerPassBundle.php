<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures\BundleCompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class BundleAsCompilerPassBundle extends AbstractBundle implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->register('foo', \stdClass::class)
            ->setPublic(true);
    }
}
