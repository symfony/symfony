<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Exception;

/**
 * The problem details class for HTTP API Problem spec (RFC 7807).
 *
 * @see https://tools.ietf.org/html/rfc7807
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class ProblemHttpException extends HttpException
{
    private $type;
    private $title;
    private $instance;
    private $extensions = [];

    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Sets the reference of the problem type.
     *
     * @param string $type A URI reference that identifies the problem type
     *
     * @return $this
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Sets the summary of the problem.
     *
     * @param string $title A short, human-readable summary of the problem type.
     *                      It should not change from occurrence to occurrence of the
     *                      problem, except for purposes of localization
     *
     * @return $this
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getInstance(): ?string
    {
        return $this->instance;
    }

    /**
     * Sets the reference of the specific problem.
     *
     * @param string $instance A URI reference that identifies the specific occurrence
     *                         of the problem
     *
     * @return $this
     */
    public function setInstance(string $instance): self
    {
        $this->instance = $instance;

        return $this;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * Sets additional members to the problem details.
     *
     * @param array $extensions A list of key-value pairs
     *
     * @return $this
     */
    public function setExtensions(array $extensions): self
    {
        $this->extensions = $extensions;

        return $this;
    }

    public function setHeaders(array $headers): self
    {
        parent::setHeaders($headers);

        return $this;
    }
}
