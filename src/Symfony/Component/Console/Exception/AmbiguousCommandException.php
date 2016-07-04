<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Exception;

/**
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class AmbiguousCommandException extends CommandNotFoundException
{
    private $command;

    public function __construct($command, $alternatives = array(), $code = null, $previous = null)
    {
        $this->command = $command;
        $message = sprintf('Command "%s" is ambiguous (%s).', $command, $this->getAbbreviationSuggestions($alternatives));

        parent::__construct($message, $alternatives, $code, $previous);
    }

    /**
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }
}
