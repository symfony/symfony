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
use Twig\Attribute\AsTwigFilter;
use Twig\Extension\AbstractExtension;
use Twig\Extension\AttributeExtension;
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

    /**
     * @deprecated since Symfony 8.2, register this class with "new AttributeExtension(HtmlSanitizerExtension::class)" instead
     */
    public function getFilters(): array
    {
        trigger_deprecation('symfony/twig-bridge', '8.2', 'The "%s::getFilters()" method is deprecated, register this class with "new %s(%s::class)" instead.', self::class, AttributeExtension::class, self::class);

        return [
            new TwigFilter('sanitize_html', $this->sanitize(...), ['is_safe' => ['html']]),
        ];
    }

    #[AsTwigFilter('sanitize_html', isSafe: ['html'])]
    public function sanitize(string $html, ?string $sanitizer = null): string
    {
        return $this->sanitizers->get($sanitizer ?? $this->defaultSanitizer)->sanitize($html);
    }
}
