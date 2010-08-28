<?php if ($trace['function']): ?>
    at <strong><abbr title="<?php echo $trace['class'] ?>"><?php echo $trace['short_class'] ?></abbr><?php echo $trace['type'] ?><?php echo $trace['function'] ?></strong>(<?php echo $view['code']->formatArgs($trace['args']) ?>)<br />
<?php endif; ?>
<?php if ($trace['file'] && $trace['line']): ?>
    in <em><?php echo $view['code']->formatFile($trace['file'], $trace['line']) ?></em>
    <a href="#" onclick="toggle('trace_<?php echo $prefix.'_'.$i ?>'); return false;">&raquo;</a><br />
    <ul class="code" id="trace_<?php echo $prefix.'_'.$i ?>" style="display: <?php echo 0 === $i ? 'block' : 'none' ?>">
        <?php echo $view['code']->fileExcerpt($trace['file'], $trace['line']) ?>
    </ul>
<?php endif; ?>
