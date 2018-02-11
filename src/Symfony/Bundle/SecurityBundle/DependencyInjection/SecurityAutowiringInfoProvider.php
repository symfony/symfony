<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Debug\AutowiringInfoProviderInterface;
use Symfony\Component\DependencyInjection\Debug\AutowiringTypeInfo;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
final class SecurityAutowiringInfoProvider implements AutowiringInfoProviderInterface
{
    public function getTypeInfos(): array
    {
        return array(
            AutowiringTypeInfo::create(GuardAuthenticatorHandler::class, 'Guard Auth Handler')
                ->setDescription('use to manually authenticate with Guard'),

            AutowiringTypeInfo::create(Security::class, 'Security')
                ->setDescription('use to check access & get the current User'),

            AutowiringTypeInfo::create(UserPasswordEncoderInterface::class, 'Password Encoder')
                ->setDescription('use to encode passwords & check them'),
        );
    }
}
