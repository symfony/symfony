<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symphony\Component\DependencyInjection\ContainerAwareInterface;
use Symphony\Component\DependencyInjection\ContainerAwareTrait;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;

class FragmentController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function indexAction(Request $request)
    {
        return $this->container->get('templating')->renderResponse('fragment.html.php', array('bar' => new Bar()));
    }

    public function inlinedAction($options, $_format)
    {
        return new Response($options['bar']->getBar().' '.$_format);
    }

    public function customFormatAction($_format)
    {
        return new Response($_format);
    }

    public function customLocaleAction(Request $request)
    {
        return new Response($request->getLocale());
    }

    public function forwardLocaleAction(Request $request)
    {
        return new Response($request->getLocale());
    }
}

class Bar
{
    private $bar = 'bar';

    public function getBar()
    {
        return $this->bar;
    }
}
