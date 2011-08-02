<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\FileUploadBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;


class DefaultController extends Controller
{
    
    public function indexAction()
    {
        $files = $this->get('request')->files->all();
        
        return $this->render('FileUploadBundle:Default:index.html.twig', array('files' => count($files)));
    }
}
