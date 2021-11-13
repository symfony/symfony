<?php
declare(strict_types=1);

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation\Groups;

final class DefaultGroupDummy
{

    public $somethingWithoutGroup;

    /**
     * @Groups({"a"})
     */
    public $somethingWithGroup;
}
