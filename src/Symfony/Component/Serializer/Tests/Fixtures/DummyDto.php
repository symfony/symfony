<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

class DummyDto
{
    #[Assert\NotBlank(groups: ['Foo'])]
    public ?string $propWithValidationGroups = null;

    #[Assert\NotBlank()]
    public ?string $randomText = null;

    #[Assert\IsTrue()]
    public bool $itMustBeTrue = true;
}
