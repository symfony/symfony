<?php echo json_encode(array(
    'error'       => array(
        'code'      => $exception->getStatusCode(),
        'message'   => $exception->getStatusText(),
))) ?>
