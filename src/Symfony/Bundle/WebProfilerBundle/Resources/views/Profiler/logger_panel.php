<h2>Logs</h2>

<?php if ($data->getLogs()): ?>
    <ul class="alt">
        <?php foreach ($data->getLogs() as $i => $log): ?>
            <li class="<?php echo $i % 2 ? 'odd' : 'even' ?><?php if ('ERR' === $log['priorityName']): ?> error<?php endif; ?>">
                <?php echo $log['priorityName'] ?>:
                <?php echo preg_replace('/("|\')(.*?)\\1/', '$1<em>$2</em>$1', $log['message']) ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <em>No logs available.</em>
<?php endif; ?>
