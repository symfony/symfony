<?php

$vars = array(
    'error'       => array(
        'code'      => $manager->getStatusCode(),
        'message'   => $manager->getName(),
        'exception' => array(
            'name'    => $manager->getName(),
            'message' => $manager->getMessage(),
            'traces'  => $manager->getTraces(),
        ),
));

if (count($managers)) {
    $vars['exceptions'] = array();
    foreach ($managers as $i => $previous) {
        $vars['exceptions'][] = array(
            'name'    => $previous->getName(),
            'message' => $previous->getMessage(),
            'traces'  => $previous->getTraces(),
        );
    }
}

echo json_encode($vars);
