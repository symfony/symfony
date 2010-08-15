<?php echo json_encode(array(
    'error'       => array(
        'code'      => $manager->getStatusCode(),
        'message'   => $manager->getMessage(),
        'debug'     => array(
            'name'    => $manager->getName(),
            'traces'  => $manager->getTraces(),
        ),
))) ?>
