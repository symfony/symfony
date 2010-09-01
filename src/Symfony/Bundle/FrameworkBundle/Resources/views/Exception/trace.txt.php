<?php if ($trace['function']): ?>
    at <?php echo $trace['class'].$trace['type'].$trace['function'] ?>(<?php echo $view->get('code')->formatArgsAsText($trace['args']) ?>)
<?php else: ?>
    at n/a
<?php endif; ?>
<?php if ($trace['file'] && $trace['line']): ?>
       in <?php echo $trace['file'] ?> line <?php echo $trace['line'] ?>
<?php endif; ?>
