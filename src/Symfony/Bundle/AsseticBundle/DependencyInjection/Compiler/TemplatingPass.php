<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * This pass removes services associated with unused templating engines.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class TemplatingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('assetic.asset_manager')) {
            return;
        }

        $engines = $container->getParameterBag()->resolveValue($container->getParameter('templating.engines'));
        $baseEngines = array('twig', 'php');
        foreach ($baseEngines as $be) {
          if (!in_array($be, $engines)) {
              $taggedServicesIds = $container->findTaggedServiceIds('assetic.templating.'.$be);
              foreach ($taggedServicesIds as $id => $attr) {
                  $container->remove($id);
              }
          }
        }
    }
}
