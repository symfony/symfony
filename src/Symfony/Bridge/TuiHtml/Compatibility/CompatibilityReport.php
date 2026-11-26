<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Compatibility;

use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Html\ElementRegistry;
use Symfony\Component\HtmlRenderer\Compatibility\Support;

/**
 * Collects what the HTML and CSS registries support into one matrix.
 *
 * The registries are the single source of truth: an element, a property or
 * a media feature registered by an application shows up here too.
 *
 * @experimental
 */
final class CompatibilityReport
{
    /**
     * @param iterable<SupportProviderInterface> $providers
     */
    public function __construct(private readonly iterable $providers)
    {
    }

    /**
     * Creates the report of what the built-in elements and CSS support.
     */
    public static function createDefault(): self
    {
        return new self([new ElementRegistry(), new CssStyleSheet()]);
    }

    /**
     * @return array<string, Support> Sorted by feature key
     */
    public function getFeatures(): array
    {
        $features = [];
        foreach ($this->providers as $provider) {
            $features = $provider->getSupport() + $features;
        }
        ksort($features);

        return $features;
    }

    /**
     * Returns how well a feature is supported, keyed like "css.properties.border-radius": unsupported when not listed.
     */
    public function getSupport(string $feature): Support
    {
        return $this->getFeatures()[$feature] ?? Support::unsupported();
    }

    public function toJson(): string
    {
        return json_encode(array_map(static fn (Support $s) => $s->toArray(), $this->getFeatures()), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n";
    }

    public function toMarkdown(): string
    {
        $markdown = "| Feature | Support | Notes |\n|---|---|---|\n";
        foreach ($this->getFeatures() as $key => $support) {
            $markdown .= \sprintf("| `%s` | %s | %s |\n", $key, $support->level->value, str_replace('|', '\|', $support->note));
        }

        return $markdown;
    }
}
