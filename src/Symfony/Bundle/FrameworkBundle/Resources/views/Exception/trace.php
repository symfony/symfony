<?php if ($trace['function']): ?>
    at <strong><abbr title="<?php echo $trace['class'] ?>"><?php echo $trace['short_class'] ?></abbr><?php echo $trace['type'] ?><?php echo $trace['function'] ?></strong>(<?php echo $view->get('code')->formatArgs($trace['args']) ?>)<br />
<?php endif; ?>
<?php if ($trace['file'] && $trace['line']): ?>
    in <em><?php echo $view->get('code')->formatFile($trace['file'], $trace['line']) ?></em>
    <a href="#" onclick="toggle('trace_<?php echo $prefix.'_'.$i ?>'); return false;">&raquo;</a><br />
    <div id="trace_<?php echo $prefix.'_'.$i ?>" class="trace" style="display: <?php echo 0 === $i ? 'block' : 'none' ?>">
        <?php echo $view->get('code')->fileExcerpt($trace['file'], $trace['line']) ?>
    </div>
<?php endif; ?>
