<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\CacheWarmer;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheWarmer\AbstractPhpFileCacheWarmer;
use Symfony\Component\Form\TypeInfoFormTypeGuesser;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Warms up the guesses of TypeInfoFormTypeGuesser for the fields of the data classes known at build time.
 *
 * @internal
 */
final class TypeInfoFormTypeGuesserCacheWarmer extends AbstractPhpFileCacheWarmer
{
    /**
     * @param array<class-string, list<string>> $fields       The names of the guessed fields, by data class
     * @param string                            $phpArrayFile The PHP file where the guesses are cached
     */
    public function __construct(
        private array $fields,
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

        // the same keys and values as at runtime, since the guesser computes them
        $guesser = new TypeInfoFormTypeGuesser($this->typeResolver, $arrayAdapter);

        foreach ($this->fields as $class => $fields) {
            try {
                foreach ($fields as $field) {
                    $guesser->guessType($class, $field);
                }
            } catch (\Exception $e) {
                $this->ignoreAutoloadException($class, $e);
            }
        }

        return true;
    }
}
