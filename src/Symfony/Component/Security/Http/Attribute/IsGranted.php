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
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class IsGranted
{
    /**
     * Sets the first argument that will be passed to isGranted().
     *
     * @var mixed
     */
    private $attributes;

    /**
     * Sets the second argument passed to isGranted().
     *
     * @var mixed
     */
    private $subject;

    /**
     * The message of the exception - has a nice default if not set.
     *
     * @var string
     */
    private $message;

    /**
     * If set, will throw Symfony\Component\HttpKernel\Exception\HttpException
     * with the given $statusCode.
     * If null, Symfony\Component\Security\Core\Exception\AccessDeniedException.
     * will be used.
     *
     * @var int|null
     */
    private $statusCode;

    /**
     * @param mixed        $subject
     */
    public function __construct(
        array|string $attributes = null,
        $subject = null,
        string $message = null,
        ?int $statusCode = null
    ) {
        $this->attributes = $attributes;
        $this->subject = $subject;
        $this->message = $message;
        $this->statusCode = $statusCode;
    }

    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
    }
}
