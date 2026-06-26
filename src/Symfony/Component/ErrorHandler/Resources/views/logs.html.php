<?php
$channelIsDefined = isset($logs[0]['channel']);

// distinct levels (highest severity first) and channels (alphabetical) feed the column filters
$levelFilter = $channelFilter = [];
foreach ($logs as $log) {
    $levelFilter[$log['priorityName']] = $log['priority'];
    if ($channelIsDefined) {
        $channelFilter[$log['channel']] = true;
    }
}
arsort($levelFilter);
$levelFilter = array_keys($levelFilter);
$channelFilter = array_keys($channelFilter);
sort($channelFilter);

// renders a column header that turns into a checkbox filter dropdown (only when there's more than
// one distinct value to filter by); otherwise it's a plain label
$renderLogFilter = function (string $name, string $label, array $values): void {
    if (\count($values) < 2) {
        echo $this->escape($label);

        return;
    } ?>
    <div class="logs-filter" data-filter="<?= $name; ?>">
        <button type="button" class="logs-filter-trigger" aria-expanded="false" aria-haspopup="true"><?= $this->escape($label); ?> <span class="logs-filter-icon"><?= $this->include('assets/images/icon-chevrons-up-down.svg'); ?></span></button>
        <div class="logs-filter-menu" hidden>
            <div class="logs-filter-options">
                <?php foreach ($values as $value) { ?>
                    <label class="logs-filter-option"><input type="checkbox" value="<?= $this->escape($value); ?>" checked><span><?= $this->escape($value); ?></span></label>
                <?php } ?>
            </div>
            <button type="button" class="logs-filter-all">Select none</button>
        </div>
    </div>
<?php }; ?>
<div class="logs <?= $channelIsDefined ? 'logs-has-channel' : ''; ?>" data-logs>
    <div class="logs-head">
        <span class="logs-col logs-col-time">Time</span>
        <div class="logs-col logs-col-level"><?php $renderLogFilter('level', 'Level', $levelFilter); ?></div>
        <?php if ($channelIsDefined) { ?><div class="logs-col logs-col-channel"><?php $renderLogFilter('channel', 'Channel', $channelFilter); ?></div><?php } ?>
        <div class="logs-col logs-head-message">
            <span>Message</span>
            <?php if (\count($logs) > 1) { // a single log needs neither expand/collapse nor search ?>
            <div class="logs-actions">
                <button type="button" class="logs-expand-all" aria-expanded="false">
                    <span class="label-show"><span class="logs-expand-icon"><?= $this->include('assets/images/icon-maximize.svg'); ?></span>Expand all</span>
                    <span class="label-hide"><span class="logs-expand-icon"><?= $this->include('assets/images/icon-minimize.svg'); ?></span>Collapse all</span>
                </button>
                <label class="logs-search-box">
                    <span class="logs-search-icon"><?= $this->include('assets/images/icon-search.svg'); ?></span>
                    <input type="search" class="logs-search" placeholder="Search logs&hellip;" aria-label="Search messages and context" autocomplete="off" spellcheck="false">
                </label>
            </div>
            <?php } ?>
        </div>
    </div>

    <div class="logs-list">
        <?php
        foreach ($logs as $log) {
            if ($log['priority'] >= 400) {
                $status = 'error';
            } elseif ($log['priority'] >= 300) {
                $status = 'warning';
            } else {
                $severity = 0;
                if (($exception = $log['context']['exception'] ?? null) instanceof \ErrorException || $exception instanceof \Symfony\Component\ErrorHandler\Exception\SilencedErrorContext) {
                    $severity = $exception->getSeverity();
                }
                $status = \E_DEPRECATED === $severity || \E_USER_DEPRECATED === $severity ? 'warning' : 'normal';
            }
            $hasDetail = (bool) $log['context'];
            // error logs are expanded by default so the failure context is visible right away
            $expanded = $hasDetail && 'error' === $status;
            $tag = $hasDetail ? 'button' : 'div';
            ?>
            <div class="log-line status-<?= $status; ?><?= $hasDetail ? ' has-detail' : ''; ?><?= $expanded ? ' is-expanded' : ''; ?>"<?= $expanded ? ' data-default-expanded="1"' : ''; ?> data-level="<?= $this->escape($log['priorityName']); ?>"<?php if ($channelIsDefined) { ?> data-channel="<?= $this->escape($log['channel']); ?>"<?php } ?> data-time="<?= $this->escape($log['timestamp_rfc3339'] ?? date(\DATE_RFC3339_EXTENDED, $log['timestamp'])); ?>">
                <<?= $tag; ?> class="log-summary"<?= $hasDetail ? ' type="button" aria-expanded="'.($expanded ? 'true' : 'false').'"' : ''; ?>>
                    <span class="log-time"><?= date('H:i:s', $log['timestamp']); ?></span>
                    <span class="log-level log-level-<?= $status; ?>"><?= $this->escape($log['priorityName']); ?></span>
                    <?php if ($channelIsDefined) { ?><span class="log-channel"><?= $this->escape($log['channel']); ?></span><?php } ?>
                    <span class="log-message break-long-words"><?= $this->formatLogMessage($log['message'], $log['context']); ?></span>
                    <span class="log-chevron"><?php if ($hasDetail) { echo $this->include('assets/images/chevron-right.svg'); } ?></span>
                </<?= $tag; ?>>
                <?php if ($hasDetail) { ?>
                <div class="log-detail">
                    <div class="log-detail-inner">
                        <pre class="log-json"><?= $this->formatLogContext($log['context']); ?></pre>
                    </div>
                </div>
                <?php } ?>
            </div>
        <?php } ?>
        <p class="logs-empty" hidden>No logs match your search criteria.</p>
    </div>
</div>
