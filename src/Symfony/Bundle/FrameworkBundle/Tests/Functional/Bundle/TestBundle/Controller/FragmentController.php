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

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\Fragment\FragmentUriGeneratorInterface;
use Twig\Environment;

class FragmentController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function indexAction(Environment $twig)
    {
        return new Response($twig->render('fragment.html.twig', ['bar' => new Bar()]));
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

    public function fragmentUriAction(Request $request, FragmentUriGeneratorInterface $fragmentUriGenerator)
    {
        return new Response($fragmentUriGenerator->generate(new ControllerReference(self::class.'::indexAction'), $request));
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
