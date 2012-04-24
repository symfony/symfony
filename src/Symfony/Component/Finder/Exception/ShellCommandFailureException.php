<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Exception;

use Symfony\Component\Finder\Adapter\AdapterInterface;
use Symfony\Component\Finder\Command;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ShellCommandFailureException extends AdapterFailureException
{
    /**
     * @var \Symfony\Component\Finder\Command
     */
    private $command;

    /**
     * @param \Symfony\Component\Finder\Adapter\AdapterInterface $adapter
     * @param \Symfony\Component\Finder\Command                  $command
     * @param \Exception|null                                    $previous
     */
    public function __construct(AdapterInterface $adapter, Command $command, \Exception $previous = null)
    {
        $this->command = $command;
        parent::__construct($adapter, 'Shell command failed: "'.$command->join().'".', $previous);
    }

    /**
     * @return \Symfony\Component\Finder\Command
     */
    public function getCommand()
    {
        return $this->command;
    }
}
