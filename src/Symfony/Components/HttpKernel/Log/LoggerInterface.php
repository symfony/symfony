<?php

namespace Symfony\Components\HttpKernel\Log;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * LoggerInterface.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface LoggerInterface
{
    public function log($message, $priority);

    public function emerg($message);

    public function alert($message);

    public function crit($message);

    public function err($message);

    public function warn($message);

    public function notice($message);

    public function info($message);

    public function debug($message);
}
