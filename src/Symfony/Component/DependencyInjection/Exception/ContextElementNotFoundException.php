<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * This exception is thrown when a non-existent context element is used.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ContextElementNotFoundException extends InvalidArgumentException
{
    private $key;

    /**
     * @param string $key
     */
    public function __construct($key)
    {
        $this->key = $key;

        parent::__construct(sprintf('Context element not found for "%s" key', $key));
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }
}
