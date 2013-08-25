<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Expression
{
    private $id;
    private $path;

    /**
     * Constructor.
     *
     * @param string $id   A service identifier
     * @param string $path A PropertyAccess path
     */
    public function __construct($id, $path)
    {
        $this->id = $id;
        $this->path = $path;
    }

    /**
     * Gets the service identifier of the expression.
     *
     * @return string The service identifier
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets the property path.
     *
     * @return string The property path
     */
    public function getPath()
    {
        return $this->path;
    }
}
