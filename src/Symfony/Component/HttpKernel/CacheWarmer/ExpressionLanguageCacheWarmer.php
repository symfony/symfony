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

use Symfony\Component\ExpressionLanguage\CompiledExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * Compiles the expressions of controller attributes, plus the ones listed for each expression language.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class ExpressionLanguageCacheWarmer extends CacheWarmer
{
    /**
     * @param iterable<string, CompiledExpressionLanguage>            $expressionLanguages Indexed by the file to compile the expressions into
     * @param list<class-string>                                      $controllers
     * @param array<string, array<class-string, array<string, bool>>> $attributes          The properties of controller attributes that hold the expressions to compile, mapped to whether their strings are expressions too, indexed by the file to compile them into
     * @param array<string, list<string>>                             $expressions         More expressions to compile, indexed by the file to compile them into
     */
    public function __construct(
        private iterable $expressionLanguages,
        private array $controllers,
        private array $attributes = [],
        private array $expressions = [],
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if (!$buildDir) {
            return [];
        }

        $instances = $this->attributes ? $this->instantiateControllerAttributes() : [];
        $files = [];

        foreach ($this->expressionLanguages as $file => $expressionLanguage) {
            $expressions = [];

            foreach ($this->attributes[$file] ?? [] as $class => $properties) {
                foreach ($instances as $instance) {
                    if (!$instance instanceof $class) {
                        continue;
                    }

                    $values = get_object_vars($instance);
                    foreach ($properties as $property => $stringsAreExpressions) {
                        if ($stringsAreExpressions && \is_string($value = $values[$property] ?? null)) {
                            $expressions[$value] = $value;
                        } else {
                            self::collectExpressions([$values[$property] ?? null], $expressions);
                        }
                    }
                }
            }

            foreach ($this->expressions[$file] ?? [] as $expression) {
                $expressions[$expression] = $expression;
            }

            if (!is_dir($dir = \dirname($file)) && !@mkdir($dir, 0o777, true) && !is_dir($dir)) {
                throw new \RuntimeException(\sprintf('Unable to create the "%s" directory.', $dir));
            }

            $this->writeCacheFile($file, $expressionLanguage->dumpCompiled($expressions));
            $files[] = $file;
        }

        return $files;
    }

    /**
     * @return list<object>
     */
    private function instantiateControllerAttributes(): array
    {
        $instances = [];
        foreach ($this->controllers as $class) {
            $r = new \ReflectionClass($class);
            $attributes = $r->getAttributes();

            foreach ($r->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                array_push($attributes, ...$method->getAttributes());

                foreach ($method->getParameters() as $parameter) {
                    array_push($attributes, ...$parameter->getAttributes());
                }
            }

            foreach ($attributes as $attribute) {
                try {
                    $instances[] = $attribute->newInstance();
                } catch (\Throwable) {
                    // the expressions of an attribute that cannot be instantiated here are left to be parsed at runtime
                }
            }
        }

        return $instances;
    }

    private static function collectExpressions(array $values, array &$expressions): void
    {
        foreach ($values as $value) {
            if ($value instanceof Expression) {
                $expressions[(string) $value] = $value;
            } elseif (\is_array($value)) {
                self::collectExpressions($value, $expressions);
            }
        }
    }
}
