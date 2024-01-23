<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Psr\Container\ContainerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class HtmlSanitizerExtension extends AbstractExtension
{
    public function __construct(
        private ContainerInterface $sanitizers,
        private string $defaultSanitizer = 'default',
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('sanitize_html', $this->sanitize(...), ['is_safe' => ['html']]),
        ];
    }

    public function sanitize(string $html, ?string $sanitizer = null): string
    {
        return $this->sanitizers->get($sanitizer ?? $this->defaultSanitizer)->sanitize($html);
    }
}
