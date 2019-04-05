<?php

namespace Symfony\Component\Serializer\Annotation;

/**
 * Annotation class for @Until().
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Arnaud Tarroux
 */
class Until
{
    /**
     * @var string
     */
    private $version;

    public function __construct(string $version)
    {
        $this->version = $version;
    }

    public function getVersion(): string
    {
        return $this->version;
    }
}
