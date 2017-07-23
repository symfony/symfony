<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Framework aware base command.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
abstract class Command extends BaseCommand
{
    public function setApplication(BaseApplication $application = null)
    {
        if (null !== $application && !$application instanceof Application) {
            throw new \InvalidArgumentException(sprintf('Application must be an instance of "%s", got "%s"', Application::class, get_class($application)));
        }

        parent::setApplication($application);
    }

    /**
     * @return Application
     */
    public function getApplication()
    {
        return parent::getApplication();
    }

    /**
     * @return KernelInterface
     */
    protected function getKernel()
    {
        return $this->getApplication()->getKernel();
    }
}
