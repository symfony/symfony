<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Exception;

/**
 * Exception class thrown when a filesystem operation failure happens
 *
 * @author Romain Neutron <imprec@gmail.com>
 * @author Christian Gärtner <christiangaertner.film@googlemail.com>
 *
 * @api
 */
class IOException extends \RuntimeException implements ExceptionInterface, IOExceptionInterface
{

    /**
     * The associated path of this exception
     * @var string
     */
    protected $path;

    public function __construct($path, $message = null, $code = 0, \Exception $previous = null)
    {
        $this->path = $path;
        parent::__construct($message, $code, $previous);
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
      return $this->path;
    }
}
