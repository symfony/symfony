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

    private $rootCustom;

    public function __construct($root, $rootCustom)
    {
        $this->root = $root;
        $this->rootCustom = $rootCustom;
    }

    public function parse($name)
    {
        $parts = explode(':', $name);
        $name = $parts[count($parts) - 1];

        if ($name[0] == '_') {
            $path = $this->rootCustom.'/'.$name;
        } else {
            $path = $this->root.'/'.$parts[count($parts) - 2].'/'.$name;
        }

        return new TemplateReference($path, 'php');
    }
}
