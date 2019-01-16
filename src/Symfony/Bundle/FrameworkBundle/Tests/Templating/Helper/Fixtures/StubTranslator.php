<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper\Fixtures;

use Symfony\Contracts\Translation\TranslatorInterface;

class StubTranslator implements TranslatorInterface
{
    public function trans($id, array $parameters = [], $domain = null, $locale = null)
    {
        return '[trans]'.$id.'[/trans]';
    }
}
