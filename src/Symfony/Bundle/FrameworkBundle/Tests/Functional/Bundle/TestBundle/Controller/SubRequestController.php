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

class SubRequestController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function indexAction($handler)
    {
        $errorUrl = $this->generateUrl('subrequest_fragment_error', ['_locale' => 'fr', '_format' => 'json']);
        $altUrl = $this->generateUrl('subrequest_fragment', ['_locale' => 'fr', '_format' => 'json']);

        // simulates a failure during the rendering of a fragment...
        // should render fr/json
        $content = $handler->render($errorUrl, 'inline', ['alt' => $altUrl]);

        // ...to check that the FragmentListener still references the right Request
        // when rendering another fragment after the error occurred
        // should render en/html instead of fr/json
        $content .= $handler->render(new ControllerReference('TestBundle:SubRequest:fragment'));

        // forces the LocaleListener to set fr for the locale...
        // should render fr/json
        $content .= $handler->render($altUrl);

        // ...and check that after the rendering, the original Request is back
        // and en is used as a locale
        // should use en/html instead of fr/json
        $content .= '--'.$this->generateUrl('subrequest_fragment');

        // The RouterListener is also tested as if it does not keep the right
        // Request in the context, a 301 would be generated
        return new Response($content);
    }

    public function fragmentAction(Request $request)
    {
        return new Response('--'.$request->getLocale().'/'.$request->getRequestFormat());
    }

    public function fragmentErrorAction()
    {
        throw new \RuntimeException('error');
    }

    protected function generateUrl($name, $arguments = [])
    {
        return $this->container->get('router')->generate($name, $arguments);
    }
}
