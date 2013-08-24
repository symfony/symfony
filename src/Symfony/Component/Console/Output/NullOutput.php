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

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

/**
 * NullOutput suppresses all output.
 *
 *     $output = new NullOutput();
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @api
 *
 * @since v2.3.0
 */
class NullOutput implements OutputInterface
{
    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function setFormatter(OutputFormatterInterface $formatter)
    {
        // do nothing
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getFormatter()
    {
        // to comply with the interface we must return a OutputFormatterInterface
        return new OutputFormatter();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function setDecorated($decorated)
    {
        // do nothing
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function isDecorated()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function setVerbosity($level)
    {
        // do nothing
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getVerbosity()
    {
        return self::VERBOSITY_QUIET;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function writeln($messages, $type = self::OUTPUT_NORMAL)
    {
        // do nothing
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function write($messages, $newline = false, $type = self::OUTPUT_NORMAL)
    {
        // do nothing
    }
}
