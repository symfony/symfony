<div class="trace trace-as-html" id="trace-box-<?php echo $index; ?>">
    <div class="trace-details">
        <div class="trace-head">
            <span class="sf-toggle" data-toggle-selector="#trace-html-<?php echo $index; ?>" data-toggle-initial="<?php echo $expand ? 'display' : ''; ?>">
                <h3 class="trace-class">
                    <span class="icon icon-close"><?php echo $this->include('assets/images/icon-minus-square-o.svg'); ?></span>
                    <span class="icon icon-open"><?php echo $this->include('assets/images/icon-plus-square-o.svg'); ?></span>

                    <span class="trace-namespace">
                        <?php echo implode('\\', array_slice(explode('\\', $exception['class']), 0, -1)); ?><?php echo count(explode('\\', $exception['class'])) > 1 ? '\\' : ''; ?>
                    </span>
                    <?php echo ($parts = explode('\\', $exception['class'])) ? end($parts) : ''; ?>
                </h3>

                <?php if ($exception['message'] && $index > 1) { ?>
                    <p class="break-long-words trace-message"><?php echo $this->escape($exception['message']); ?></p>
                <?php } ?>
            </span>
        </div>

        <div id="trace-html-<?php echo $index; ?>" class="sf-toggle-content">
        <?php
        $isFirstUserCode = true;
        foreach ($exception['trace'] as $i => $trace) {
            $isVendorTrace = $trace['file'] && (false !== mb_strpos($trace['file'], '/vendor/') || false !== mb_strpos($trace['file'], '/var/cache/'));
            $displayCodeSnippet = $isFirstUserCode && !$isVendorTrace;
            if ($displayCodeSnippet) {
                $isFirstUserCode = false;
            } ?>
            <div class="trace-line <?php echo $isVendorTrace ? 'trace-from-vendor' : ''; ?>">
                <?php echo $this->include('views/trace.html.php', [
                    'prefix' => $index,
                    'i' => $i,
                    'trace' => $trace,
                    'style' => $isVendorTrace ? 'compact' : ($displayCodeSnippet ? 'expanded' : ''),
                ]); ?>
            </div>
            <?php
        } ?>
        </div>
    </div>
</div>
