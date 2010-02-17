<?php echo json_encode(array(
  'error'       => array(
    'code'      => $code,
    'message'   => $message,
    'debug'     => array(
      'name'    => $name,
      'message' => $message,
      'traces'  => $traces,
    ),
))) ?>
