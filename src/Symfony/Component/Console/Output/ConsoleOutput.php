<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Output;

/**
 * ConsoleOutput is the default class for all CLI output. It uses STDOUT.
 *
 * This class is a convenient wrapper around `StreamOutput`.
 *
 *     $output = new ConsoleOutput();
 *
 * This is equivalent to:
 *
 *     $output = new StreamOutput(fopen('php://stdout', 'w'));
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ConsoleOutput extends StreamOutput
{
    /**
     * Constructor.
     *
     * @param integer $verbosity The verbosity level (self::VERBOSITY_QUIET, self::VERBOSITY_NORMAL, self::VERBOSITY_VERBOSE)
     * @param Boolean $decorated Whether to decorate messages or not (null for auto-guessing)
     */
    public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = null)
    {
        parent::__construct(fopen('php://stdout', 'w'), $verbosity, $decorated);
    }
}
