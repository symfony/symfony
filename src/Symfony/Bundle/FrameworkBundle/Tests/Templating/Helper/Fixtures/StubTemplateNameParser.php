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

    private $rootTheme;

    public function __construct($root, $rootTheme)
    {
        $this->root = $root;
        $this->rootTheme = $rootTheme;
    }

    public function parse($name)
    {
        list($bundle, $controller, $template) = explode(':', $name, 3);

        if ('_' == $template[0]) {
            $path = $this->rootTheme.'/Custom/'.$template;
        } elseif ('TestBundle' === $bundle) {
            $path = $this->rootTheme.'/'.$controller.'/'.$template;
        } else {
            $path = $this->root.'/'.$controller.'/'.$template;
        }

        return new TemplateReference($path, 'php');
    }
}
