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
 * The resource was not found because the URI scheme is not allowed.
 *
 * This exception should trigger an HTTP 404 response or a redirect to the correct scheme in your application code.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
class SchemeNotAllowedException extends ResourceNotFoundException
{
    /**
     * @var string[]
     */
    private $allowedSchemes = array();

    public function __construct(array $allowedSchemes, $message = '', $code = 0, \Exception $previous = null)
    {
        $this->allowedSchemes = array_map('strtolower', $allowedSchemes);

        parent::__construct($message, $code, $previous);
    }

    /**
     * Gets the allowed URI schemes.
     *
     * @return string[]
     */
    public function getAllowedSchemes()
    {
        return $this->allowedSchemes;
    }
}
