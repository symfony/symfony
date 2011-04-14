<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper\Fixtures;

use Symfony\Component\Templating\TemplateNameParserInterface;
use Symfony\Component\Templating\TemplateReference;

class StubTemplateNameParser implements TemplateNameParserInterface
{
    private $root;

    public function __construct($root)
    {
        $this->root = $root;
    }

    public function parse($name)
    {
        $parts = explode(':', $name);
        $name = $parts[count($parts)-1];

        return new TemplateReference($this->root.'/'.$name, 'php');
    }
}