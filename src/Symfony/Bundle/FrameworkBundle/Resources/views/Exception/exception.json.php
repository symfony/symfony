<?php

$vars = array(
    'error'       => array(
        'code'      => $exception->getStatusCode(),
        'message'   => $exception->getMessage(),
        'exception' => array(
            'name'    => $exception->getClass(),
            'message' => $exception->getMessage(),
            'trace'   => $exception->getTrace(),
        ),
));

if (count($exception->getPreviouses())) {
    $vars['exceptions'] = array();
    foreach ($exception->getPreviouses() as $i => $previous) {
        $vars['exceptions'][] = array(
            'name'    => $previous->getClass(),
            'message' => $previous->getMessage(),
            'trace'   => $previous->getTrace(),
        );
    }
}

echo json_encode($vars);
