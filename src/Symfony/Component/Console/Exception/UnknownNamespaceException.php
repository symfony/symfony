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
class UnknownNamespaceException extends CommandNotFoundException
{
    private $namespace;

    public function __construct($namespace, $alternatives = array(), $code = null, $previous = null)
    {
        $this->namespace = $namespace;

        $message = sprintf('There are no commands defined in the "%s" namespace.', $namespace);

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
    public function getNamespace()
    {
        return $this->namespace;
    }
}
