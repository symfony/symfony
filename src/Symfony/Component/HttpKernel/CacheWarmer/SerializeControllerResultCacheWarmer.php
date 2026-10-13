<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheWarmer;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Resolves the return type of the controllers whose result #[Serialize] streams without mapping it.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class SerializeControllerResultCacheWarmer extends AbstractPhpFileCacheWarmer
{
    /**
     * @param list<class-string> $controllers
     * @param string             $phpArrayFile The PHP file where the types are cached
     */
    public function __construct(
        private array $controllers,
        string $phpArrayFile,
        private ?TypeResolverInterface $typeResolver = null,
    ) {
        parent::__construct($phpArrayFile);
    }

    protected function doWarmUp(string $cacheDir, ArrayAdapter $arrayAdapter, ?string $buildDir = null): bool
    {
        if (!$buildDir) {
            return false;
        }

        $this->typeResolver ??= TypeResolver::create();

        foreach ($this->controllers as $controller) {
            $class = new \ReflectionClass($controller);
            $classAttributes = $class->getAttributes(Serialize::class);

            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                // an attribute on the method takes precedence over the one on the class
                if (!$attributes = $method->getAttributes(Serialize::class) ?: $classAttributes) {
                    continue;
                }

                try {
                    $serialize = $attributes[0]->newInstance();

                    if (!$serialize->stream || null !== $serialize->map) {
                        continue;
                    }

                    $type = $this->typeResolver->resolve($method);
                } catch (\Throwable) {
                    // the listener reports the error when the controller is called
                    continue;
                }

                $arrayAdapter->save($arrayAdapter->getItem(rawurlencode($method->class.'::'.$method->name))->set($type));
            }
        }

        return true;
    }
}
