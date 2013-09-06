<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerAware;

class FragmentController extends ContainerAware
{
    public function indexAction(Request $request)
    {
        $actions = $this->container->get('templating')->get('actions');

        $html1 = $actions->render($actions->controller('TestBundle:Fragment:inlined', array(
            'options' => array(
                'bar' => new Bar(),
                'eleven' => 11,
            ),
        )));

        $html2 = $actions->render($actions->controller('TestBundle:Fragment:customformat', array('_format' => 'html')));

        $html3 = $actions->render($actions->controller('TestBundle:Fragment:customlocale', array('_locale' => 'es')));

        $request->setLocale('fr');
        $html4 = $actions->render($actions->controller('TestBundle:Fragment:forwardlocale'));

        return new Response($html1.'--'.$html2.'--'.$html3.'--'.$html4);
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
