<?php

namespace {{ namespace }}\{{ bundle }}\Controller;

use Symfony\Bundle\FrameworkBundle\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('{{ bundle }}:Default:index');
    }
}
