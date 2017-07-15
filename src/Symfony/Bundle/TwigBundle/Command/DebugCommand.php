<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Command;

@trigger_error(sprintf('The %s class is deprecated since version 3.4 and will be removed in 4.0. Use Symfony\Bridge\Twig\Command\DebugCommand instead.', DebugCommand::class), E_USER_DEPRECATED);

use Symfony\Bridge\Twig\Command\DebugCommand as BaseDebugCommand;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Lists twig functions, filters, globals and tests present in the current project.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @deprecated since version 3.4, to be removed in 4.0.
 */
final class DebugCommand extends BaseDebugCommand implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * {@inheritdoc}
     */
    protected function getTwigEnvironment()
    {
        return $this->container->get('twig');
    }
}
