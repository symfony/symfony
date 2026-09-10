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

use Symfony\Component\Translation\DependencyInjection\TranslationLintCommandPass as BaseTranslationLintCommandPass;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', 'Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslationLintCommandPass', BaseTranslationLintCommandPass::class);

class_alias(BaseTranslationLintCommandPass::class, 'Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\TranslationLintCommandPass');
