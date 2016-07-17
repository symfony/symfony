<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation as Serializer;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CompositionDummy
{
    public $name;

    /**
     * @Serializer\Type("\Symfony\Component\Serializer\Tests\Fixtures\CompositionChildDummy")
     */
    private $child;

    public function __construct($withValues = false)
    {
        if ($withValues) {
            $this->name = 'Foobar';
            $this->child = new CompositionChildDummy(true);
        }
    }

    /**
     * @return Car
     */
    public function getChild()
    {
        return $this->child;
    }
}
