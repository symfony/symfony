<?php

namespace Symfony\Bridge\Twig\Tests\Extension\Fixtures;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @author Jesse Rushlow <jr@rushlow.dev>
 */
class SerializerModelFixture
{
    /**
     * @Groups({"read"})
     */
    public $name = 'howdy';

    public $title = 'fixture';
}
