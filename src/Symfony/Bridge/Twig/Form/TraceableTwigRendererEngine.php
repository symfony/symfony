<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Form;

use Symfony\Component\Form\AbstractRendererEngine;
use Symfony\Component\Form\Extension\DataCollector\FormDataCollectorInterface;
use Symfony\Component\Form\FormRendererEngineInterface;
use Symfony\Component\Form\FormView;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;
use Twig\Token;

/**
 * Records how each form block is resolved and passes it to the form data collector.
 *
 * @author Benjamin Georgeault <git@wedgesama.fr>
 */
final class TraceableTwigRendererEngine implements FormRendererEngineInterface, ResetInterface
{
    /** @var array<string, list<mixed>> */
    private array $themes = [];

    /** @var list<string>|null */
    private ?array $lookupHierarchy = null;

    /** @var list<array{view: FormView, suffix: string, hierarchy: list<string>, matched: list<array{block: string, template: ?string, path: ?string, line: ?int}>}> */
    private array $stack = [];

    /**
     * Keyed by view object: object ids are reused once views are freed, and the cache key of a view does not identify its ancestors.
     *
     * @var \WeakMap<FormView, array{themes: list<Template>, key: string}>
     */
    private \WeakMap $resolvedThemes;

    /** @var array<string, array<string, int>> */
    private array $blockLines = [];

    /** @var array<string, array{template: ?string, path: ?string, line: ?int}> */
    private array $definers = [];

    public function __construct(
        private FormRendererEngineInterface $engine,
        private Environment $environment,
        private array $defaultThemes,
        private FormDataCollectorInterface $dataCollector,
    ) {
        $this->resolvedThemes = new \WeakMap();
    }

    public function setTheme(FormView $view, mixed $themes, bool $useDefaultThemes = true): void
    {
        $this->engine->setTheme($view, $themes, $useDefaultThemes);

        $this->themes[$view->vars[AbstractRendererEngine::CACHE_KEY_VAR]] = \is_array($themes) ? array_values($themes) : [$themes];
        $this->resolvedThemes = new \WeakMap();
    }

    public function getResourceForBlockName(FormView $view, string $blockName): mixed
    {
        $this->lookupHierarchy = null;

        return $this->engine->getResourceForBlockName($view, $blockName);
    }

    public function getResourceForBlockNameHierarchy(FormView $view, array $blockNameHierarchy, int $hierarchyLevel): mixed
    {
        $this->lookupHierarchy = $blockNameHierarchy;

        return $this->engine->getResourceForBlockNameHierarchy($view, $blockNameHierarchy, $hierarchyLevel);
    }

    public function getResourceHierarchyLevel(FormView $view, array $blockNameHierarchy, int $hierarchyLevel): int|false
    {
        return $this->engine->getResourceHierarchyLevel($view, $blockNameHierarchy, $hierarchyLevel);
    }

    public function renderBlock(FormView $view, mixed $resource, string $blockName, array $variables = []): string
    {
        if (!method_exists($this->dataCollector, 'collectRenderingTrace')) {
            return $this->engine->renderBlock($view, $resource, $blockName, $variables);
        }

        $hierarchy = $this->lookupHierarchy;
        $this->lookupHierarchy = null;

        if (null !== $hierarchy) {
            $suffix = substr($hierarchy[0], \strlen($view->vars['block_prefixes'][0]) + 1);
        } else {
            $suffix = $blockName;
            $hierarchy = [$blockName];
        }

        $matched = ['block' => $blockName] + $this->getDefiner($view, $blockName);

        foreach ($this->stack as $i => $entry) {
            if ($entry['view'] === $view && $entry['suffix'] === $suffix) {
                // a block calling the same helper again resumes one level up the hierarchy, so it comes first in hierarchy order
                array_unshift($this->stack[$i]['matched'], $matched);

                return $this->engine->renderBlock($view, $resource, $blockName, $variables);
            }
        }

        $this->stack[] = ['view' => $view, 'suffix' => $suffix, 'hierarchy' => $hierarchy, 'matched' => [$matched]];

        try {
            $html = $this->engine->renderBlock($view, $resource, $blockName, $variables);
        } finally {
            $entry = array_pop($this->stack);
        }

        $this->dataCollector->collectRenderingTrace($view, ['blocks' => [$suffix => ['hierarchy' => $entry['hierarchy'], 'matched' => $entry['matched']]]]);

        return $html;
    }

    public function reset(): void
    {
        if ($this->engine instanceof ResetInterface) {
            $this->engine->reset();
        }

        $this->themes = [];
        $this->lookupHierarchy = null;
        $this->stack = [];
        $this->resolvedThemes = new \WeakMap();
        $this->blockLines = [];
        $this->definers = [];
    }

    /**
     * Returns the themes in the order the engine searches them: the view, its ancestors, then the default themes.
     *
     * The default themes are listed even when "only" disabled them, because the engine then never resolves a block that only they define.
     *
     * @return array{themes: list<Template>, key: string}
     */
    private function resolveThemes(FormView $view): array
    {
        $themes = [];

        for ($current = $view; null !== $current; $current = $current->parent) {
            foreach (array_reverse($this->themes[$current->vars[AbstractRendererEngine::CACHE_KEY_VAR]] ?? []) as $theme) {
                $themes[] = $theme;
            }
        }

        foreach (array_reverse($this->defaultThemes) as $theme) {
            $themes[] = $theme;
        }

        $themes = array_values(array_filter(array_map($this->resolveTheme(...), $themes)));

        return [
            'themes' => $themes,
            'key' => implode("\0", array_map(static fn (Template $template) => $template->getTemplateName(), $themes)),
        ];
    }

    /**
     * @return array{template: ?string, path: ?string, line: ?int}
     */
    private function getDefiner(FormView $view, string $blockName): array
    {
        $resolved = $this->resolvedThemes[$view] ??= $this->resolveThemes($view);

        return $this->definers[$resolved['key']."\0".$blockName] ??= $this->findDefiner($resolved['themes'], $blockName);
    }

    /**
     * @param Template[] $themes
     *
     * @return array{template: ?string, path: ?string, line: ?int}
     */
    private function findDefiner(array $themes, string $blockName): array
    {
        $context = $this->environment->getGlobals();

        foreach ($themes as $template) {
            do {
                if (isset($template->getBlocks()[$blockName])) {
                    [$definer, $method] = $template->getBlocks()[$blockName];
                    $source = $this->getSource($definer);

                    return [
                        'template' => $definer->getTemplateName(),
                        'path' => $source->getPath() ?: null,
                        // a block imported with "use ... with foo as bar" is declared under its original name
                        'line' => $this->findBlockLine($source, str_starts_with($method, 'block_') ? substr($method, 6) : $blockName),
                    ];
                }
            } while (false !== $template = $template->getParent($context));
        }

        return ['template' => null, 'path' => null, 'line' => null];
    }

    /**
     * Compiled templates only embed their code in debug mode, so the loader is asked when the code is missing.
     */
    private function getSource(Template $template): Source
    {
        $source = $template->getSourceContext();

        if ('' !== $source->getCode()) {
            return $source;
        }

        try {
            return $this->environment->getLoader()->getSourceContext($template->getTemplateName());
        } catch (LoaderError) {
            return $source;
        }
    }

    private function findBlockLine(Source $source, string $blockName): ?int
    {
        return ($this->blockLines[$source->getName()] ??= $this->indexBlockLines($source))[$blockName] ?? null;
    }

    /**
     * @return array<string, int> The line of each block tag of the template, keyed by block name
     */
    private function indexBlockLines(Source $source): array
    {
        if ('' === $source->getCode()) {
            return [];
        }

        try {
            $stream = $this->environment->tokenize($source);
        } catch (SyntaxError) {
            return [];
        }

        $lines = [];

        while (!$stream->isEOF()) {
            $token = $stream->next();

            if ($token->test(Token::BLOCK_START_TYPE) && $stream->test(Token::NAME_TYPE, 'block') && $stream->look()->test(Token::NAME_TYPE)) {
                $lines[$stream->look()->getValue()] ??= $token->getLine();
            }
        }

        return $lines;
    }

    private function resolveTheme(mixed $theme): ?Template
    {
        return match (true) {
            \is_string($theme) => $this->environment->load($theme)->unwrap($this->environment),
            $theme instanceof TemplateWrapper => $theme->unwrap($this->environment),
            $theme instanceof Template => $theme,
            default => null,
        };
    }
}
