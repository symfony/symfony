<?php

namespace Symfony\Bundle\ZendBundle\Logger;

use Zend\Log\Logger as BaseLogger;
use Symfony\Components\HttpKernel\LoggerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Logger.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Logger extends BaseLogger implements LoggerInterface
{
    public function emerg($message)
    {
        return parent::log($message, 0);
    }

    public function alert($message)
    {
        return parent::log($message, 1);
    }

    public function crit($message)
    {
        return parent::log($message, 2);
    }

    public function err($message)
    {
        return parent::log($message, 3);
    }

    public function warn($message)
    {
        return parent::log($message, 4);
    }

    public function notice($message)
    {
        return parent::log($message, 5);
    }

    public function info($message)
    {
        return parent::log($message, 6);
    }

    public function debug($message)
    {
        return parent::log($message, 7);
    }
}
