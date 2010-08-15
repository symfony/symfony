<?php if ($trace['function']): ?>
    at <strong><?php echo $trace['class'] ?><?php echo $trace['type'] ?><?php echo $trace['function'] ?></strong>(<?php echo $view->code->formatArgs($trace['args']) ?>)<br />
<?php endif; ?>
<?php if ($trace['file'] && $trace['line']): ?>
    in <em><?php echo $view->code->formatFile($trace['file'], $trace['line']) ?></em> line <?php echo $trace['line'] ?>
    <a href="#" onclick="toggle('trace_<?php echo $i ?>'); return false;">...</a><br />
    <ul class="code" id="trace_<?php echo $i ?>" style="display: <?php echo 0 === $i ? 'block' : 'none' ?>">
        <?php echo $view->code->fileExcerpt($trace['file'], $trace['line']) ?>
    </ul>
<?php endif; ?>
