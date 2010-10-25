<?php

namespace Symfony\Component\Templating\Renderer;

use Symfony\Component\Templating\Storage\Storage;
use Symfony\Component\Templating\Storage\FileStorage;
use Symfony\Component\Templating\Storage\StringStorage;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PhpRenderer is a renderer for PHP templates.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class PhpRenderer extends Renderer
{
    /**
     * Evaluates a template.
     *
     * @param Storage $template   The template to render
     * @param array   $parameters An array of parameters to pass to the template
     *
     * @return string|false The evaluated template, or false if the renderer is unable to render the template
     */
    public function evaluate(Storage $template, array $parameters = array())
    {
        $__template__ = $template;
        if ($__template__ instanceof FileStorage) {
            extract($parameters);
            $view = $this->engine;
            ob_start();
            require $__template__;

            return ob_get_clean();
        } elseif ($__template__ instanceof StringStorage) {
            extract($parameters);
            $view = $this->engine;
            ob_start();
            eval('; ?>'.$__template__.'<?php ;');

            return ob_get_clean();
        }

        return false;
    }
}
