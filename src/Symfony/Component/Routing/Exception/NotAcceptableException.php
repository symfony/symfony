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

use Symfony\Component\Routing\RequestAcceptance;

/**
 * The resource was found but the Accept-* header does not match requirement.
 *
 * This exception should trigger an HTTP 406 response in your application code.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class NotAcceptableException extends \RuntimeException implements ExceptionInterface
{
    /**
     * @var array
     */
    protected $variables;

    /**
     * @param array           $variables
     * @param string          $message
     * @param int             $code
     * @param \Exception|null $previous
     */
    public function __construct(array $variables, $message, $code = 0, \Exception $previous = null)
    {
        $this->variables = $variables;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array
     */
    public function getVariables()
    {
        return $this->variables;
    }
}
