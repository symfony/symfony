<?php

namespace Symfony\Bundle\FrameworkBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class AjaxDataCollector extends DataCollector
{

    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        // all collecting is done client side
    }

    public function getName()
    {
        return 'ajax';
    }

}