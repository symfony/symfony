<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension\Fixtures;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class StubTranslator implements TranslatorInterface
{
    public function trans($id, array $parameters = [], $domain = null, $locale = null): string
    {
        foreach ($parameters as $k => $v) {
            if ($v instanceof TranslatableInterface) {
                $parameters[$k] = $v->trans($this, $locale);
            }
        }

        return '[trans]'.strtr($id, $parameters).'[/trans]';
    }

    public function getLocale(): string
    {
        return 'en';
    }
}
