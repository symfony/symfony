<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\AuthenticatorBundle;

use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AuthenticatorBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->configureSecurityExtension($container);
    }

    private function configureSecurityExtension(ContainerBuilder $container): void
    {
        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');

        $extension->addAuthenticatorFactory(new DummyFormLoginFactory());
    }
}
