<div class="trace trace-as-html" id="trace-box-<?= $index; ?>">
    <div class="trace-details">
        <div class="trace-head">
            <span class="sf-toggle" data-toggle-selector="#trace-html-<?= $index; ?>" data-toggle-initial="<?= $expand ? 'display' : ''; ?>">
                <h3 class="trace-class">
                    <span class="icon icon-close"><?= $this->include('assets/images/icon-minus-square-o.svg'); ?></span>
                    <span class="icon icon-open"><?= $this->include('assets/images/icon-plus-square-o.svg'); ?></span>

                    <span class="trace-namespace">
                        <?= implode('\\', array_slice(explode('\\', $exception['class']), 0, -1)); ?><?= count(explode('\\', $exception['class'])) > 1 ? '\\' : ''; ?>
                    </span>
                    <?= ($parts = explode('\\', $exception['class'])) ? end($parts) : ''; ?>
                </h3>

                <?php if ($exception['message'] && $index > 1) { ?>
                    <p class="break-long-words trace-message"><?= $this->escape($exception['message']); ?></p>
                <?php } ?>
            </span>
        </div>

        <div id="trace-html-<?= $index; ?>" class="sf-toggle-content">
        <?php
        $isFirstUserCode = true;
        foreach ($exception['trace'] as $i => $trace) {
            $isVendorTrace = $trace['file'] && (false !== mb_strpos($trace['file'], '/vendor/') || false !== mb_strpos($trace['file'], '/var/cache/'));
            $displayCodeSnippet = $isFirstUserCode && !$isVendorTrace;
            if ($displayCodeSnippet) {
                $isFirstUserCode = false;
            } ?>
            <div class="trace-line <?= $isVendorTrace ? 'trace-from-vendor' : ''; ?>">
                <?= $this->include('views/trace.html.php', [
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
