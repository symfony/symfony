<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap\Resolver;

class PackageResolverRegistry
{
    private array $resolvers = [];

    public function __construct(private ?PackageResolverInterface $defaultResolver = null, iterable $resolvers = [])
    {
        foreach ($resolvers as $name => $resolver) {
            $this->addResolver($name, $resolver);
        }
    }

    public function setDefaultResolver(?PackageResolverInterface $defaultResolver): void
    {
        $this->defaultResolver = $defaultResolver;
    }

    public function addResolver(string $name, PackageResolverInterface $resolver): void
    {
        $this->resolvers[$name] = $resolver;
    }

    public function getResolver(?string $name = null): PackageResolverInterface
    {
        if (null === $name) {
            if (null === $this->defaultResolver) {
                throw new \LogicException('No default resolver is defined.');
            }

            return $this->defaultResolver;
        }

        if (!isset($this->resolvers[$name])) {
            throw new \InvalidArgumentException(sprintf('The resolver "%s" does not exist.', $name));
        }

        return $this->resolvers[$name];
    }
}
