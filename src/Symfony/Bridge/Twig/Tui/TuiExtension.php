<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tui;

use Symfony\Component\Tui\Style\Density;
use Symfony\Component\Tui\Style\MediaContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigTest;

/**
 * Provides the "condensed" test, for components whose markup changes with the density.
 *
 *     {% if attributes is condensed %}...{% endif %}
 *
 * The test is true when the value (component attributes, an array of
 * attributes or a density name) sets data-density="condensed". Without a
 * data-density, the density of the application decides.
 *
 * Styling differences belong in CSS, with the :condensed pseudo-class.
 *
 * @experimental
 */
final class TuiExtension extends AbstractExtension
{
    /**
     * @param Density|\Closure(): (Density|MediaContext) $density The density of the application, or a closure returning it, like $tui->getMediaContext(...)
     */
    public function __construct(
        private readonly Density|\Closure $density = Density::Regular,
    ) {
    }

    public function getTests(): array
    {
        return [
            new TwigTest('condensed', $this->isCondensed(...)),
        ];
    }

    public function isCondensed(mixed $value): bool
    {
        $density = match (true) {
            \is_string($value) => $value,
            \is_array($value) => $value['data-density'] ?? null,
            \is_object($value) && method_exists($value, 'all') => $value->all()['data-density'] ?? null,
            $value instanceof \ArrayAccess => $value['data-density'] ?? null,
            default => null,
        };

        if (null !== $density = \is_string($density) ? Density::tryFrom($density) : null) {
            return Density::Condensed === $density;
        }

        $global = $this->density instanceof \Closure ? ($this->density)() : $this->density;
        if ($global instanceof MediaContext) {
            $global = $global->density;
        }

        return Density::Condensed === $global;
    }
}
