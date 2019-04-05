<?php

namespace Symfony\Component\Serializer\Annotation;

/**
 * Annotation class for @Since().
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Arnaud Tarroux
 */
class Since
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
