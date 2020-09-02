<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

@trigger_error(sprintf('The %s class is deprecated since Symfony 3.4 and will be removed in 4.0. Use Symfony\Component\Translation\DependencyInjection\TranslationDumperPass instead.', TranslationDumperPass::class), \E_USER_DEPRECATED);

use Symfony\Component\Translation\DependencyInjection\TranslationDumperPass as BaseTranslationDumperPass;

/**
 * Adds tagged translation.formatter services to translation writer.
 *
 * @deprecated since version 3.4, to be removed in 4.0. Use {@link BaseTranslationDumperPass instead}.
 */
class TranslationDumperPass extends BaseTranslationDumperPass
{
}
