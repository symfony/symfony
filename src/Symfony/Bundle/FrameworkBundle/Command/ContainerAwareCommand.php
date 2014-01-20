<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\DependencyInjection\ContainerAwareCommand as BaseContainerAwareCommand;

/**
 * Command.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class ContainerAwareCommand extends BaseContainerAwareCommand
{
    /**
     * @return ContainerInterface
     */
    protected function getContainer()
    {
        if (null === parent::getContainer()) {
            $this->container = $this->getApplication()->getKernel()->getContainer();
        }

        return parent::getContainer();
    }
}
