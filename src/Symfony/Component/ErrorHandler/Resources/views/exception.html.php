<?php $exceptionAsArray = $exception->toArray(); ?>
<?php $exceptionCount = \count($exceptionAsArray); ?>

<div class="container">
    <div class="exceptions-header">
        <?php if ($exceptionCount > 1) { ?>
            <?php // long chains keep only 3 exceptions visible (the first two and the root cause); the rest hide behind a "show more" control
            $collapseChain = $exceptionCount >= 4; ?>
            <ol class="exception-chain" aria-label="Exception chain">
                <?php
                // most recent first: the primary exception is expanded on top, followed by the
                // previous ones down to the root cause (same order as the Symfony profiler and console)
                $position = 0;
                foreach ($exceptionAsArray as $i => $e) {
                    ++$position;
                    $isExpanded = 0 === $i; // the primary (most recent) exception is expanded by default
                    $isHidden = $collapseChain && 2 < $position && $position < $exceptionCount;
                    ?>
                    <li class="exc-item <?= $isExpanded ? 'is-expanded' : ''; ?> <?= $isHidden ? 'exc-item-hidden' : ''; ?>">
                        <button type="button" class="exc-summary" data-exception-index="<?= $i; ?>" aria-expanded="<?= $isExpanded ? 'true' : 'false'; ?>" aria-controls="exc-detail-<?= $i; ?>">
                            <?php
                            $separator = strrpos($e['class'], '\\');
                            $separator = false === $separator ? 0 : $separator + 1;
                            $namespace = substr($e['class'], 0, $separator);
                            $shortClass = substr($e['class'], $separator);
                            ?>
                            <span class="exc-item-marker" aria-hidden="true"><?= $position; ?></span>
                            <span class="exc-item-class" title="<?= $this->escape($e['class']); ?>"><?php if ('' !== $namespace) { ?><span class="exc-item-namespace"><?= $this->escape($namespace); ?></span><?php } ?><span class="exc-item-name"><?= $this->escape($shortClass); ?></span></span>
                            <?php if ('' !== $e['message']) { ?>
                                <span class="exc-item-message"><?= $this->escape($e['message']); ?></span>
                            <?php } ?>
                        </button>
                        <div id="exc-detail-<?= $i; ?>" class="exc-detail">
                            <div class="exc-detail-inner">
                                <?php if ('' !== $e['message']) { ?>
                                    <div class="exc-message break-long-words<?= mb_strlen($e['message']) > 180 ? ' exc-message-long' : ''; ?>"><?= $this->formatFileFromText(nl2br($this->escape($e['message']))); ?></div>
                                <?php } else { ?>
                                    <?php // no message: show the FQCN as the message so the expanded detail isn't empty ?>
                                    <div class="exc-message break-long-words"><?= $this->escape($e['class']); ?></div>
                                <?php } ?>
                                <?php if (0 === $i) { ?>
                                    <p class="exc-status"><span class="exc-status-code">HTTP <?= $statusCode; ?></span> <?= $statusText; ?></p>
                                <?php } ?>
                            </div>
                        </div>
                    </li>
                    <?php if ($collapseChain && 2 === $position) { ?>
                        <li class="exc-item-more">
                            <button type="button" class="exc-more-btn">
                                <span class="exc-item-marker exc-item-marker-more" aria-hidden="true">&hellip;</span>
                                Show <?= $exceptionCount - 3; ?> more exception<?= $exceptionCount - 3 > 1 ? 's' : ''; ?>
                            </button>
                        </li>
                    <?php } ?>
                <?php } ?>
            </ol>
        <?php } else { ?>
            <div class="exc-single">
                <?php if ('' !== $exceptionMessage) { ?>
                    <p class="exc-subtitle"><?= $this->abbrClass($exceptionAsArray[0]['class']); ?></p>
                    <div class="exc-message break-long-words <?= mb_strlen($exceptionAsArray[0]['message']) > 180 ? 'exc-message-long' : ''; ?>"><?= $this->formatFileFromText(nl2br($exceptionMessage)); ?></div>
                <?php } else { ?>
                    <?php // no message: promote the FQCN to the message and drop the (now redundant) subtitle ?>
                    <div class="exc-message break-long-words"><?= $this->escape($exceptionAsArray[0]['class']); ?></div>
                <?php } ?>
                <p class="exc-status"><span class="exc-status-code">HTTP <?= $statusCode; ?></span> <?= $statusText; ?></p>
            </div>
        <?php } ?>
    </div>
</div>

<div class="container exception-content">
    <?php foreach ($exceptionAsArray as $i => $e) { ?>
        <div class="exc-panel <?= 0 === $i ? 'is-active' : ''; ?>" data-exception-index="<?= $i; ?>">
            <?= $this->include('views/traces.html.php', [
                'exception' => $e,
                'index' => $i + 1,
            ]); ?>
        </div>
    <?php } ?>
</div>

<?php // the profiler scopes this fragment's stylesheet to itself, so the live region has to sit inside the fragment ?>
<p class="sr-only" role="status" data-announcer></p>
