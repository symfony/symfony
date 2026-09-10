<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Translation;

use Symfony\Component\Translation\DependencyInjection\Translator as BaseTranslator;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', 'Symfony\Bundle\FrameworkBundle\Translation\Translator', BaseTranslator::class);

class_alias(BaseTranslator::class, 'Symfony\Bundle\FrameworkBundle\Translation\Translator');
