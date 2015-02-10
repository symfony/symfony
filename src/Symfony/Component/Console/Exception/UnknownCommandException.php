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
class UnknownCommandException extends CommandNotFoundException
{
    private $command;

    public function __construct($command, $alternatives = array(), $code = null, $previous = null)
    {
        $this->command = $command;

        $message = sprintf('Command "%s" is not defined.', $command);

        if ($alternatives) {
            if (1 == count($alternatives)) {
                $message .= "\n\nDid you mean this?\n    ";
            } else {
                $message .= "\n\nDid you mean one of these?\n    ";
            }

            $message .= implode("\n    ", $alternatives);
        }

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
