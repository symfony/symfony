<?php echo json_encode(array(
    'error'       => array(
        'code'      => $manager->getStatusCode(),
        'message'   => $manager->getStatusText(),
))) ?>
