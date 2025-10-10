<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Application;

class DescriptorApplication2 extends Application
{
    public function __construct()
    {
        parent::__construct('My Symfony application', 'v1.0');
        $this->addCommand(new DescriptorCommand1());
        $this->addCommand(new DescriptorCommand2());
        $this->addCommand(new DescriptorCommand3());
        $this->addCommand(new DescriptorCommand4());
    }
}
