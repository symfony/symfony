<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\CsrfHttpBasicLoginBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Response;

class IndexController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function __invoke()
    {
        $form = $this->container->get('form.factory')
            ->createNamedBuilder(
                '',
                'Symfony\Component\Form\Extension\Core\Type\FormType',
                null,
                ['csrf_token_id' => 'foo']
            )
            ->add('submit', 'Symfony\Component\Form\Extension\Core\Type\SubmitType')
            ->getForm()
            ->handleRequest($this->container->get('request_stack')->getCurrentRequest())
        ;

        if (!$form->isSubmitted()) {
            return new Response($this->container->get('twig')->render('@CsrfHttpBasicLogin/form.html.twig', [
                'form' => $form->createView(),
            ]));
        }

        return new Response('', $form->isValid() ? 200 : 400);
    }
}
