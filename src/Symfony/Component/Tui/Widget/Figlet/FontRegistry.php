<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget\Figlet;

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * Registry for FIGlet fonts.
 *
 * Maps font names to file paths and lazily loads FigletFont instances.
 * Bundled fonts (big, small, slant, standard, mini) are registered
 * by default. Custom fonts can be registered by name:
 *
 *     $registry = new FontRegistry();
 *     $registry->register('custom', '/path/to/custom.flf');
 *
 * Fonts are referenced by name throughout the Style system:
 *
 *     $stylesheet->addRule('.title', new Style(font: 'custom'));
 *     $widget->addStyleClass('font-custom');
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class FontRegistry
{
    private const string BUNDLED_FONTS_DIR = __DIR__.'/fonts';
    private const array BUNDLED_FONTS = ['big', 'small', 'slant', 'standard', 'mini'];

    /** @var array<string, string> name → file path */
    private array $paths = [];

    /** @var array<string, FigletFont> name → loaded font (cache) */
    private array $fonts = [];

    public function __construct()
    {
        foreach (self::BUNDLED_FONTS as $name) {
            $this->paths[$name] = self::BUNDLED_FONTS_DIR.'/'.$name.'.flf';
        }
    }

    /**
     * Register a font by name with a path to a .flf file.
     *
     * @return $this
     */
    public function register(string $name, string $path): static
    {
        $this->paths[$name] = $path;
        unset($this->fonts[$name]); // invalidate cache if re-registering

        return $this;
    }

    /**
     * Load and return a font by name.
     *
     * @throws InvalidArgumentException if the font name is not registered
     */
    public function get(string $name): FigletFont
    {
        if (isset($this->fonts[$name])) {
            return $this->fonts[$name];
        }

        if (!isset($this->paths[$name])) {
            throw new InvalidArgumentException(\sprintf('Font "%s" is not registered. Available fonts: "%s".', $name, implode('", "', array_keys($this->paths))));
        }

        return $this->fonts[$name] = FigletFont::load($this->paths[$name]);
    }

    /**
     * Check whether a font name is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->paths[$name]);
    }

    /**
     * Get all registered font names.
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return array_keys($this->paths);
    }
}
