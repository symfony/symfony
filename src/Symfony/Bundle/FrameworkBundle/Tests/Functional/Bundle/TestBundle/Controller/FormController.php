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

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\Form\FormInterface;

class FormController extends ContainerAware
{
    public function phpChoiceSingleRequiredAction()
    {
        return $this->render('TestBundle:Form:form.html.php', $this->createForm());
    }

    public function twigChoiceSingleRequiredAction()
    {
        return $this->render('TestBundle:Form:form.html.twig', $this->createForm());
    }

    public function phpChoiceMultipleRequiredAction()
    {
        return $this->render('TestBundle:Form:form.html.php', $this->createForm(array('multiple' => true)));
    }

    public function twigChoiceMultipleRequiredAction()
    {
        return $this->render('TestBundle:Form:form.html.twig', $this->createForm(array('multiple' => true)));
    }

    public function phpChoiceSingleNotRequiredAction()
    {
        return $this->render('TestBundle:Form:form.html.php', $this->createForm(array('required' => false)));
    }

    public function twigChoiceSingleNotRequiredAction()
    {
        return $this->render('TestBundle:Form:form.html.twig', $this->createForm(array('required' => false)));
    }

    public function phpChoiceMultipleNotRequiredAction()
    {
        return $this->render('TestBundle:Form:form.html.php', $this->createForm(array('multiple' => true, 'required' => false)));
    }

    public function twigChoiceMultipleNotRequiredAction()
    {
        return $this->render('TestBundle:Form:form.html.twig', $this->createForm(array('multiple' => true, 'required' => false)));
    }

    public function phpChoiceSingleNotRequiredEmptyValueAction()
    {
        return $this->render('TestBundle:Form:form.html.php', $this->createForm(array('required' => false, 'empty_value' => 'Empty label')));
    }

    public function twigChoiceSingleNotRequiredEmptyValueAction()
    {
        return $this->render('TestBundle:Form:form.html.twig', $this->createForm(array('required' => false, 'empty_value' => 'Empty label')));
    }

    protected function render($view, FormInterface $form)
    {
        return $this->container->get('templating')->renderResponse($view, array('form' => $form->createView()));
    }

    protected function createForm(array $options = array())
    {
        $options = array_merge(array(
            'choices' => $this->getChoices(),
        ), $options);

        $form = $this->container->get('form.factory')->create()
            ->add('choice', 'choice', $options)
        ;

        return $form;
    }

    protected function getChoices()
    {
        return array('choice1' => 'Choice 1', 'choice2' => 'Choice 2', 'choice3' => 'Choice 3');
    }
}
