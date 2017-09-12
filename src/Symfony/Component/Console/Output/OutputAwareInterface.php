<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Output;

/**
 * OutputAwareInterface should be implemented by classes that depends on the console output.
 *
 * @author Adam Sentner <adam@adamsentner.com>
 */
interface OutputAwareInterface
{
    /**
     * Set the console output.
     *
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output);
}
