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

final class DefaultParametersTranslator implements TranslatorInterface
{
    /**
     * @param array<string, string> $defaultParameters
     */
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly array $defaultParameters,
    ) {
    }

    public function trans(string $id, array $parameters = [], string $domain = null, string $locale = null): string
    {
        $parameters = array_replace($this->defaultParameters, $parameters);

        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }
}
