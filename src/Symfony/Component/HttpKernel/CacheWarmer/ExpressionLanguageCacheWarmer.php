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
use Symfony\Component\ExpressionLanguage\Parser;

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
     * @param iterable<string, CompiledExpressionLanguage> $expressionLanguages Indexed by their service id
     * @param array<string, string>                        $files               The files to compile the expressions into, indexed by the service id of their expression language
     */
    public function __construct(
        private iterable $expressionLanguages,
        private array $files,
        private ExpressionCollector $collector,
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

        $files = [];

        foreach ($this->expressionLanguages as $id => $expressionLanguage) {
            if (!$file = $this->files[$id] ?? null) {
                continue;
            }

            $expressions = [];

            foreach ($this->collector->getAttributeExpressions($id) as $collected) {
                $expressions[(string) $collected->expression] = $collected->expression;
            }

            // listed expressions come from the configuration: an invalid one fails the warmup instead of being left out
            foreach ($this->collector->getListedExpressions($id) as $collected) {
                $expressionLanguage->lint($collected->expression, $collected->variables ?? [], null === $collected->variables ? Parser::IGNORE_UNKNOWN_VARIABLES : 0);
                $expressions[(string) $collected->expression] = $collected->expression;
            }

            if (!is_dir($dir = \dirname($file)) && !@mkdir($dir, 0o777, true) && !is_dir($dir)) {
                throw new \RuntimeException(\sprintf('Unable to create the "%s" directory.', $dir));
            }

            $this->writeCacheFile($file, $expressionLanguage->dumpCompiled($expressions));
            $files[] = $file;
        }

        return $files;
    }
}
