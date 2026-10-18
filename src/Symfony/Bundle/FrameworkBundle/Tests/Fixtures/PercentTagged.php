<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.%weird%.tag')]
class PercentTagged
{
}
