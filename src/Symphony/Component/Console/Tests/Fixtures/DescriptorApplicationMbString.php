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

class DescriptorApplicationMbString extends Application
{
    public function __construct()
    {
        parent::__construct('MbString åpplicätion');

        $this->add(new DescriptorCommandMbString());
    }
}
