<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\HtmlSanitizer;

use Psr\Container\ContainerInterface;
use Symfony\Component\Form\AbstractExtension;

/**
 * Integrates the HtmlSanitizer component with the Form library.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class HtmlSanitizerExtension extends AbstractExtension
{
    public function __construct(
        private ContainerInterface $sanitizers,
        private string $defaultSanitizer = 'default',
    ) {
    }

    protected function loadTypeExtensions(): array
    {
        return [
            new Type\TextTypeHtmlSanitizerExtension($this->sanitizers, $this->defaultSanitizer),
        ];
    }
}
