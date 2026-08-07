<?php if ($exception['trace']) { ?>
<pre class="stacktrace">
<?php
    echo $this->escape($exception['class']).":\n";
    if ($exception['message']) {
        echo $this->escape($exception['message'])."\n";
    }

    foreach ($exception['trace'] as $trace) {
        echo "\n  ";
        if ($trace['function']) {
            echo $this->escape('at '.$trace['class'].$trace['type'].$trace['function']).'('.(isset($trace['args']) ? $this->formatArgsAsText($trace['args']) : '').')';
        }
        if ($trace['file'] && $trace['line']) {
            echo($trace['function'] ? "\n     (" : 'at ').strtr(strip_tags($this->formatFile($trace['file'], $trace['line'])), [' at line '.$trace['line'] => '']).':'.$trace['line'].($trace['function'] ? ')' : '');
        }
    }
?>
</pre>
<?php } ?>
