<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests;


use Symfony\Component\Console\Application;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Prompt;

class PromptTest extends \PHPUnit_Framework_TestCase
{

    public function testRender()
    {
        $application = new Application('foo', 'bar');
        $prompt = new Prompt($application);
        $formatter = new OutputFormatter();

        $this->assertEquals('foo > ', $prompt->render($formatter), 'render() returns the application name');
    }
}

