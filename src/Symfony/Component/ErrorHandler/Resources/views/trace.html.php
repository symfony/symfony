<div class="trace-line-header break-long-words <?php echo $trace['file'] ? 'sf-toggle' : ''; ?>" data-toggle-selector="#trace-html-<?php echo $prefix; ?>-<?php echo $i; ?>" data-toggle-initial="<?php echo 'expanded' === $style ? 'display' : ''; ?>">
    <?php if ($trace['file']) { ?>
        <span class="icon icon-close"><?php echo $this->include('assets/images/icon-minus-square.svg'); ?></span>
        <span class="icon icon-open"><?php echo $this->include('assets/images/icon-plus-square.svg'); ?></span>
    <?php } ?>

    <?php if ('compact' !== $style && $trace['function']) { ?>
        <span class="trace-class"><?php echo $this->abbrClass($trace['class']); ?></span><?php if ($trace['type']) { ?><span class="trace-type"><?php echo $trace['type']; ?></span><?php } ?><span class="trace-method"><?php echo $trace['function']; ?></span><?php if (isset($trace['args'])) { ?><span class="trace-arguments">(<?php echo $this->formatArgs($trace['args']); ?>)</span><?php } ?>
    <?php } ?>

    <?php if ($trace['file']) { ?>
        <?php
        $lineNumber = $trace['line'] ?: 1;
        $fileLink = $this->getFileLink($trace['file'], $lineNumber);
        $filePath = strtr(strip_tags($this->formatFile($trace['file'], $lineNumber)), [' at line '.$lineNumber => '']);
        $filePathParts = explode(\DIRECTORY_SEPARATOR, $filePath);
        ?>
        <span class="block trace-file-path">
            in
            <a href="<?php echo $fileLink; ?>">
                <?php echo implode(\DIRECTORY_SEPARATOR, array_slice($filePathParts, 0, -1)).\DIRECTORY_SEPARATOR; ?><strong><?php echo end($filePathParts); ?></strong>
            </a>
            <?php if ('compact' === $style && $trace['function']) { ?>
                <span class="trace-type"><?php echo $trace['type']; ?></span>
                <span class="trace-method"><?php echo $trace['function']; ?></span>
            <?php } ?>
            (line <?php echo $lineNumber; ?>)
        </span>
    <?php } ?>
</div>
<?php if ($trace['file']) { ?>
    <div id="trace-html-<?php echo $prefix.'-'.$i; ?>" class="trace-code sf-toggle-content">
        <?php echo strtr($this->fileExcerpt($trace['file'], $trace['line'], 5), [
            '#DD0000' => 'var(--highlight-string)',
            '#007700' => 'var(--highlight-keyword)',
            '#0000BB' => 'var(--highlight-default)',
            '#FF8000' => 'var(--highlight-comment)',
        ]); ?>
    </div>
<?php } ?>
