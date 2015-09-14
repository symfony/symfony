<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\SyntaxAware;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * A ContainerBuilder that facilitates using the shortcut (e.g. @service_name) syntax.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class SyntaxAwareContainerBuilder extends ContainerBuilder
{
    public function __construct(ParameterBagInterface $parameterBag = null)
    {
        parent::__construct($parameterBag);

        $this->parameterBag = new SyntaxAwareParameterBag($this->parameterBag);
    }

    protected function createDefinition($class)
    {
        return new SyntaxAwareDefinition($class);
    }
}
