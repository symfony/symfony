<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\ExtensionWithoutConfigTestBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

class ExtensionWithoutConfigTestExtension implements ExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    public function getNamespace(): string
    {
        return '';
    }

    public function getXsdValidationBasePath(): string|false
    {
        return false;
    }

    public function getAlias(): string
    {
        return 'extension_without_config_test';
    }
}
