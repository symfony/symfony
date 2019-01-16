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

use Symfony\Component\Translation\TranslatorInterface;

class StubTranslator implements TranslatorInterface
{
    public function trans($id, array $parameters = [], $domain = null, $locale = null)
    {
        return '[trans]'.$id.'[/trans]';
    }

    public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null)
    {
        return '[trans]'.$id.'[/trans]';
    }

    public function setLocale($locale)
    {
    }

    public function getLocale()
    {
    }
}
