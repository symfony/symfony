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
class AmbiguousNamespaceException extends CommandNotFoundException
{
    private $namespace;

    public function __construct($namespace, $alternatives = array(), $code = null, $previous = null)
    {
        $this->command = $namespace;

        $message = sprintf('The namespace "%s" is ambiguous (%s).', $namespace, $this->getAbbreviationSuggestions($alternatives));

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
