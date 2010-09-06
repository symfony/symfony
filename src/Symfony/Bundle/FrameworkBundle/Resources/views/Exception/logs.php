<ol>
    <?php foreach ($logs as $log): ?>
        <li<?php if ('ERR' === $log['priorityName']): ?> class="error"<?php endif; ?>>
            <?php echo $log['priorityName'] ?>:
            <?php echo $log['message'] ?>
        </li>
    <?php endforeach; ?>
</ol>
