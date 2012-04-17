<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Console\Event;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;

/**
 * Allows to edit input and output of a command.
 *
 * Call setOutput if you need to modify the output object. The propagation of
 * this event is stopped as soon as a new output is set.
 *
 * @author Francesco Levorato <git@flevour.net>
 */
class ConsoleInitEvent extends KernelEvent
{

    /**
     * The input received by the command.
     *
     * @var Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    /**
     * The output object used by the command.
     *
     * @var Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    /**
     * Sets an output object and stops event propagation
     *
     * @param Symfony\Component\Console\Output\OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;

        $this->stopPropagation();
    }

    /**
     * Returns the input object
     *
     * @return Symfony\Component\Console\Input\InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * Returns the output object
     *
     * @return Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

}