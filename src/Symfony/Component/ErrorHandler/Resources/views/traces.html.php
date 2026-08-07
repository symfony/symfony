<?php
// vendor frames (dependencies, framework, compiled cache, fileless internal calls) are hidden by
// default behind a toggle; only the application's own frames stay visible (cf. isVendorTraceFile())
$isVendorFrame = [];
foreach ($exception['trace'] as $i => $trace) {
    $isVendorFrame[$i] = $this->isVendorTraceFile($trace['file']);
}

// when every frame is a vendor frame, force the first one visible so the trace isn't empty
$forceFirstVisible = !\in_array(false, $isVendorFrame, true);

$hiddenVendorCount = 0;
foreach ($isVendorFrame as $i => $isVendor) {
    if ($isVendor && !($forceFirstVisible && 0 === $i)) {
        ++$hiddenVendorCount;
    }
}
?>

<div class="trace-header">
    <h2>Stack Trace</h2>
    <div class="sf-tabs sf-tabs-sm trace-format-tabs">
        <div class="tab-navigation" role="tablist" aria-label="Stack trace format">
            <span class="tab-glider" aria-hidden="true"></span>
            <button type="button" class="tab-control" role="tab" id="trace-fmt-<?= $index; ?>-label" aria-controls="trace-fmt-<?= $index; ?>" aria-selected="true">Formatted</button>
            <button type="button" class="tab-control" role="tab" id="trace-raw-<?= $index; ?>-label" aria-controls="trace-raw-<?= $index; ?>" aria-selected="false" tabindex="-1">Raw</button>
        </div>
    </div>
    <button type="button" class="copy-btn" data-copy-text hidden>
        <span class="icon"><?= $this->include('assets/images/icon-copy.svg'); ?></span>
        <span class="copy-label">Copy as text</span>
    </button>
</div>

<div class="tab-panel" id="trace-fmt-<?= $index; ?>" role="tabpanel" aria-labelledby="trace-fmt-<?= $index; ?>-label">
<div class="trace trace-as-html" id="trace-box-<?= $index; ?>">
    <div class="trace-details">
        <?php
        $isFirstUserCode = true;
        foreach ($exception['trace'] as $i => $trace) {
            $isVendorTrace = $isVendorFrame[$i];
            // vendor frames are hidden by default, except the forced-visible first frame
            $isHidden = $isVendorTrace && !($forceFirstVisible && 0 === $i);
            $displayCodeSnippet = ($isFirstUserCode && !$isVendorTrace) || ($forceFirstVisible && 0 === $i);
            if ($displayCodeSnippet) {
                $isFirstUserCode = false;
            } ?>
            <div class="trace-line <?= $isHidden ? 'trace-from-vendor' : ''; ?> <?= $trace['file'] ? 'sf-toggle '.($displayCodeSnippet ? 'sf-toggle-on' : 'sf-toggle-off') : ''; ?>"<?php if ($trace['file']) { ?> data-toggle-selector="#trace-html-<?= $index; ?>-<?= $i; ?>" data-toggle-initial="<?= $displayCodeSnippet ? 'display' : ''; ?>"<?php } ?>>
                <?= $this->include('views/trace.html.php', [
                    'prefix' => $index,
                    'i' => $i,
                    'trace' => $trace,
                    'displayCodeSnippet' => $displayCodeSnippet,
                ]); ?>
            </div>
            <?php
        } ?>
        <?php if ($hiddenVendorCount > 0) { ?>
            <button type="button" class="trace-vendor-toggle" aria-expanded="false">
                <span class="label-show"><span class="trace-vendor-toggle-icon"><?= $this->include('assets/images/icon-maximize.svg'); ?></span>Show <?= $hiddenVendorCount; ?> vendor frame<?= $hiddenVendorCount > 1 ? 's' : ''; ?>&hellip;</span>
                <span class="label-hide"><span class="trace-vendor-toggle-icon"><?= $this->include('assets/images/icon-minimize.svg'); ?></span>Hide <?= $hiddenVendorCount; ?> vendor frame<?= $hiddenVendorCount > 1 ? 's' : ''; ?></span>
            </button>
        <?php } ?>
    </div>
</div>
</div>

<div class="tab-panel" id="trace-raw-<?= $index; ?>" role="tabpanel" aria-labelledby="trace-raw-<?= $index; ?>-label" hidden>
    <div class="trace trace-as-html">
        <div class="trace-details trace-raw-card">
            <button type="button" class="trace-raw-copy hidden" aria-label="Copy stack trace">
                <span class="icon-copy-default"><?= $this->include('assets/images/icon-copy.svg'); ?></span>
                <span class="icon-copy-success"><?= $this->include('assets/images/icon-copy-check.svg'); ?></span>
            </button>
            <div class="trace-raw"><?= $this->include('views/traces_text.html.php', ['exception' => $exception]); ?></div>
        </div>
    </div>
</div>

<?php if ($this->showExceptionProperties($exception['class']) && ($data = $exception['data'] ?? null) && \count($data)) { ?>
    <details class="exception-properties-wrapper"<?= $this->isVendorExceptionClass($exception['class']) ? '' : ' open'; ?>>
        <summary class="exception-properties-summary">
            Exception properties <span class="h2-badge"><?= \count($data); ?></span>
            <span class="exception-properties-chevron"><?= $this->include('assets/images/chevron-right.svg'); ?></span>
        </summary>
        <div class="exception-properties">
            <?= $this->dumpExceptionProperties($data) ?>
        </div>
    </details>
<?php } ?>
