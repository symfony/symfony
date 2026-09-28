<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Extractor;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Extractor\Visitor\AbstractVisitor;
use Symfony\Component\Translation\Extractor\Visitor\CallResolver;
use Symfony\Component\Translation\Extractor\Visitor\VariableResolver;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * PhpAstExtractor extracts translation messages from a PHP AST.
 *
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
final class PhpAstExtractor extends AbstractFileExtractor implements ExtractorInterface
{
    private Parser $parser;

    /**
     * @var array<string, Node[]>
     */
    private array $parsedFiles = [];

    /**
     * @var list<string>
     */
    private array $extractedPaths = [];

    public function __construct(
        /**
         * @param iterable<AbstractVisitor&NodeVisitor> $visitors
         */
        private readonly iterable $visitors,
        private string $prefix = '',
    ) {
        if (!class_exists(ParserFactory::class)) {
            throw new \LogicException(\sprintf('You cannot use "%s" as the "nikic/php-parser" package is not installed. Try running "composer require nikic/php-parser".', static::class));
        }

        $this->parser = (new ParserFactory())->createForHostVersion();
    }

    public function extract(iterable|string $resource, MessageCatalogue $catalogue): void
    {
        if (!\is_string($resource)) {
            // iterables may be consumed only once, while they are needed to both list and scope the extracted files
            $resource = iterator_to_array($resource, false);
        }

        $this->parsedFiles = [];
        $this->extractedPaths = [];

        foreach ((array) $resource as $path) {
            if (false !== $path = realpath((string) $path)) {
                $this->extractedPaths[] = $path;
            }
        }

        foreach ($this->extractFiles($resource) as $file) {
            $traverser = new NodeTraverser();

            foreach ($this->visitors as $visitor) {
                $visitor->initialize($catalogue, $file, $this->prefix);
                $traverser->addVisitor($visitor);
            }

            $traverser->traverse($this->parse((string) $file));
        }

        $this->parsedFiles = [];
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    protected function canBeExtracted(string $file): bool
    {
        return 'php' === pathinfo($file, \PATHINFO_EXTENSION)
            && $this->isFile($file)
            && preg_match('/\bt\(|->trans\(|TranslatableMessage|Symfony\\\\Component\\\\Validator\\\\Constraints/i', file_get_contents($file));
    }

    protected function extractFromDirectory(array|string $resource): iterable|Finder
    {
        if (!class_exists(Finder::class)) {
            throw new \LogicException(\sprintf('You cannot use "%s" as the "symfony/finder" package is not installed. Try running "composer require symfony/finder".', static::class));
        }

        return (new Finder())->files()->name('*.php')->in($resource);
    }

    /**
     * @return Node[]
     */
    private function parse(string $file): array
    {
        return (new NodeTraverser(
            // This is needed to resolve namespaces in class methods/constants.
            new NodeVisitor\NameResolver(),
            // This is needed to extract messages passed to translation methods as variables.
            new VariableResolver(),
            // This is needed to extract messages returned by methods.
            new CallResolver($this->parseDeclaringFile(...)),
        ))->traverse($this->parser->parse(file_get_contents($file)));
    }

    /**
     * Parses and caches the files declaring the methods called by the extracted files.
     *
     * They are often reused (e.g. enums), unlike the extracted files, which are parsed once and would only take up memory.
     * Files outside of the extracted paths are skipped, so that messages owned by vendor packages are not extracted.
     *
     * @return Node[]
     */
    private function parseDeclaringFile(string $file): array
    {
        return $this->parsedFiles[$file] ??= $this->isExtracted($file) ? $this->parse($file) : [];
    }

    private function isExtracted(string $file): bool
    {
        foreach ($this->extractedPaths as $path) {
            if ($file === $path || str_starts_with($file, rtrim($path, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }
}
