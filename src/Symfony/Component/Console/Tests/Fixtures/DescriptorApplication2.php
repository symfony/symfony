<?php

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Application;

class DescriptorApplication2 extends Application
{
    public function __construct()
    {
        parent::__construct();
        $this->add(new DescriptorCommand1());
        $this->add(new DescriptorCommand2());
    }
}
