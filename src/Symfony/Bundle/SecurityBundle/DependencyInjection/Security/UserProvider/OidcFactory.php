<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\UserProvider;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * OidcFactory creates services for the OIDC user provider.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
class OidcFactory implements UserProviderFactoryInterface
{
    public function create(ContainerBuilder $container, string $id, array $config): void
    {
        $container->setDefinition($id, new ChildDefinition('security.user.provider.oidc'));
    }

    public function getKey(): string
    {
        return 'oidc';
    }

    public function addConfiguration(NodeDefinition $builder): void
    {
        \assert($builder instanceof ArrayNodeDefinition);

        $builder
            ->beforeNormalization()
                ->ifTrue(static fn ($v) => null === $v || (\is_array($v) && [] === $v))
                ->then(static fn () => ['enabled' => true])
            ->end()
        ;

        $builder
            ->children()
                ->booleanNode('enabled')->defaultTrue()->info('Internal marker; the OIDC provider has no configuration options.')->end()
            ->end()
        ;
    }
}
