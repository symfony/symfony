<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Console\Tests\Fixtures;

use Symphony\Component\Console\Application;

class DescriptorApplication2 extends Application
{
    public function __construct()
    {
        parent::__construct('My Symphony application', 'v1.0');
        $this->add(new DescriptorCommand1());
        $this->add(new DescriptorCommand2());
        $this->add(new DescriptorCommand3());
        $this->add(new DescriptorCommand4());
    }
}
