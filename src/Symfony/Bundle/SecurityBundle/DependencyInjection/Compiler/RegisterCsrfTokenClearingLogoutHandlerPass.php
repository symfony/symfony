<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

trigger_deprecation('symfony/security-bundle', '5.1', 'The "%s" class is deprecated.', RegisterCsrfTokenClearingLogoutHandlerPass::class);

/**
 * @deprecated since symfony/security-bundle 5.1
 */
class RegisterCsrfTokenClearingLogoutHandlerPass extends RegisterCsrfFeaturesPass
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('security.csrf.token_storage')) {
            return;
        }

        $this->registerLogoutHandler($container);
    }
}
