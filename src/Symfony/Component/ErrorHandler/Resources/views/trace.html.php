<div class="trace-line-header break-long-words">
    <?php if ($trace['function']) { ?>
        <span class="trace-class"><?= $this->abbrClass($trace['class']); ?></span><?php if ($trace['type']) { ?><span class="trace-type"><?= $trace['type']; ?></span><?php } ?><span class="trace-method"><?= $trace['function']; ?></span><?php if (isset($trace['args'])) { ?><span class="trace-arguments">(<?= $this->formatArgs($trace['args'], true); ?>)</span><?php } ?>
    <?php } ?>

    <?php if ($trace['file']) { ?>
        <?php
        $lineNumber = $trace['line'] ?: 1;
        $fileLink = $this->fileLinkFormat->format($trace['file'], $lineNumber);
        $filePath = strtr(strip_tags($this->formatFile($trace['file'], $lineNumber)), [' at line '.$lineNumber => '']);
        $filePathParts = explode(\DIRECTORY_SEPARATOR, $filePath);
        ?>
        <span class="trace-file-path">
            in
            <a href="<?= $fileLink; ?>">
                <?= implode(\DIRECTORY_SEPARATOR, array_slice($filePathParts, 0, -1)).\DIRECTORY_SEPARATOR; ?><strong><?= end($filePathParts); ?></strong>
            </a>
            (line <?= $lineNumber; ?>)
            <button type="button" class="icon icon-copy hidden" aria-label="Copy file path" data-clipboard-text="<?php echo implode(\DIRECTORY_SEPARATOR, $filePathParts).':'.$lineNumber; ?>">
                <span class="icon-copy-default"><?php echo $this->include('assets/images/icon-copy.svg'); ?></span>
                <span class="icon-copy-success"><?php echo $this->include('assets/images/icon-copy-check.svg'); ?></span>
            </button>
        </span>
    <?php } ?>
</div>
<?php if ($trace['file']) { ?>
    <div id="trace-html-<?= $prefix.'-'.$i; ?>" class="trace-code sf-toggle-content <?= $displayCodeSnippet ? 'sf-toggle-visible' : 'sf-toggle-hidden'; ?>">
        <div class="trace-code-inner"><?= strtr($this->fileExcerpt($trace['file'], $trace['line'], 5), [
            '#DD0000' => 'var(--code-syntax-string)',
            '#007700' => 'var(--code-syntax-keyword)',
            '#0000BB' => 'var(--code-foreground)',
            '#FF8000' => 'var(--code-syntax-comment)',
        ]); ?></div>
    </div>
<?php } ?>
