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
 * The resource was found but the Accept-* header does not match requirement.
 *
 * This exception should trigger an HTTP 406 response in your application code.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class NegotiationFailureException extends \RuntimeException implements ExceptionInterface
{
    /**
     * @var array
     */
    private $negotiatedParameters;

    /**
     * @param array           $negotiatedParameters
     * @param array           $failures
     * @param int             $code
     * @param \Exception|null $previous
     */
    public function __construct(array $negotiatedParameters, array $failures, $code = 0, \Exception $previous = null)
    {
        $this->negotiatedParameters = $negotiatedParameters;
        parent::__construct(sprintf('Negotiation failed for "%s" parameters.', implode(', ', $failures)), $code, $previous);
    }

    /**
     * @return array
     */
    public function getNegotiatedParameters()
    {
        return $this->negotiatedParameters;
    }
}
