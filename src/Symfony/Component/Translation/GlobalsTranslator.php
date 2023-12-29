<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Contracts\Translation\TranslatorInterface;

final class GlobalsTranslator implements TranslatorInterface
{
    private array $globals = [];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function trans(string $id, array $parameters = [], string $domain = null, string $locale = null): string
    {
        $parameters = array_replace($this->globals, $parameters);

        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    public function addGlobal(string $name, mixed $value): void
    {
        $this->globals[$name] = $value;
    }
}
