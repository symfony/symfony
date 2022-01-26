<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Attribute;

/**
 * The Security class handles the Security attribute.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Security
{
    /**
     * The expression evaluated to allow or deny access.
     *
     * @var string
     */
    private $expression;

    /**
     * If set, will throw Symfony\Component\HttpKernel\Exception\HttpException
     * with the given $statusCode.
     * If null, Symfony\Component\Security\Core\Exception\AccessDeniedException.
     * will be used.
     *
     * @var int|null
     */
    protected $statusCode;

    /**
     * The message of the exception.
     *
     * @var string
     */
    protected $message = 'Access denied.';

    /**
     * @param array|string $data
     */
    public function __construct(
        string $expression = null,
        string $message = null,
        ?int $statusCode = null
    ) {
        $this->expression = $expression;
        if (null !== $message) {
            $this->message = $message;
        }
        $this->statusCode = $statusCode;
    }

    public function getExpression()
    {
        return $this->expression;
    }

    public function setExpression($expression)
    {
        $this->expression = $expression;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }
}
