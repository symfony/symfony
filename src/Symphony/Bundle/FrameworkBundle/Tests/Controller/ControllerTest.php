<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Controller;

use Symphony\Bundle\FrameworkBundle\Controller\Controller;
use Symphony\Component\HttpFoundation\File\File;

class ControllerTest extends ControllerTraitTest
{
    protected function createController()
    {
        return new TestController();
    }
}

class TestController extends Controller
{
    use TestControllerTrait;
}
