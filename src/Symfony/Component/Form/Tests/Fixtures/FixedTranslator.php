<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Contracts\Translation\TranslatorInterface;

class FixedTranslator implements TranslatorInterface
{
    private $translations;

    public function __construct(array $translations)
    {
        $this->translations = $translations;
    }

    public function trans(string $id, array $parameters = [], string $domain = null, string $locale = null): string
    {
        return $this->translations[$id] ?? $id;
    }

    public function getLocale(): string
    {
        return 'en';
    }
}
