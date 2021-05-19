<?php

namespace Symfony\Bridge\Twig\Tests\Extension\Fixtures;

use Symfony\Component\Serializer\Annotation\Groups;

/**

 */
class SerializerModelFixture
{
    /**
     * @Groups({"read"})
     */
    public $name = 'howdy';

    public $title = 'fixture';
}
