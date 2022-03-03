<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Validation;

use Symfony\Bundle\FrameworkBundle\ArgumentResolver\UserInputInterface;
use Symfony\Component\Validator\Constraints as Assert;

class DummyDto implements UserInputInterface
{
    #[Assert\NotBlank()]
    public ?string $randomText = null;

    #[Assert\IsTrue()]
    public bool $itMustBeTrue = true;
}
