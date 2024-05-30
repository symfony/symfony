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

use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\String\Inflector\FrenchInflector;
use Symfony\Component\String\Inflector\InflectorInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
final class StringRuntime implements RuntimeExtensionInterface
{
    private FrenchInflector $frenchInflector;
    private EnglishInflector $englishInflector;

    public function pluralize($value, string $lang, bool $singleResult = false): array|string
    {
        return match (true) {
            $singleResult => $this->getInflector($lang)->pluralize($value)[0],
            default => $this->getInflector($lang)->pluralize($value),
        };
    }

    public function singularize($value, string $lang, bool $singleResult = false): array|string
    {
        return match (true) {
            $singleResult => $this->getInflector($lang)->singularize($value)[0],
            default => $this->getInflector($lang)->singularize($value),
        };
    }

    private function getInflector(string $lang): InflectorInterface
    {
        return match ($lang) {
            'fr' => $this->frenchInflector ?? $this->frenchInflector = new FrenchInflector(),
            'en' => $this->englishInflector ?? $this->englishInflector = new EnglishInflector(),
            default => throw new \InvalidArgumentException(sprintf('Language "%s" is not supported.', $lang)),
        };
    }
}
