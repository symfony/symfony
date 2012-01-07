<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Exception;

/**
 * The resource was found but the hostname is not allowed.
 *
 * This exception should trigger an HTTP 404 response in your application code.
 *
 * @author Gunnar Lium <post@gunnarlium.com>
 *
 */
class HostnameNotAllowedException extends \RuntimeException implements ExceptionInterface
{
    protected $allowedHostnames;

    public function __construct(array $allowedHostnames, $message = null, $code = 0, \Exception $previous = null)
    {
        $this->allowedHostnames = array_map('strtolower', $allowedHostnames);

        parent::__construct($message, $code, $previous);
    }

    public function getAllowedHostnames()
    {
        return $this->allowedHostnames;
    }
}
