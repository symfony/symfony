<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

@trigger_error('The '.__NAMESPACE__.'\DefinitionDecorator class is deprecated since Symfony 3.3 and will be removed in 4.0. Use the Symfony\Component\DependencyInjection\ChildDefinition class instead.', E_USER_DEPRECATED);

class_exists(ChildDefinition::class);

if (false) {
    /**
     * This definition decorates another definition.
     *
     * @author Johannes M. Schmitt <schmittjoh@gmail.com>
     *
     * @deprecated The DefinitionDecorator class is deprecated since version 3.3 and will be removed in 4.0. Use the Symfony\Component\DependencyInjection\ChildDefinition class instead.
     */
    class DefinitionDecorator extends Definition
    {
    }
}
