<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

@trigger_error('The '.__NAMESPACE__.'\ResolveDefinitionTemplatesPass class is deprecated since Symfony 3.4 and will be removed in 4.0. Use the ResolveChildDefinitionsPass class instead.', E_USER_DEPRECATED);

class_exists(ResolveChildDefinitionsPass::class);
