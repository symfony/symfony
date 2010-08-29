<h2>Queries</h2>

<?php if (0 == $data->getQueryCount()): ?>
    <em>No queries.</em>
<?php else: ?>
    <ul class="alt">
        <?php foreach ($data->getQueries() as $i => $query): ?>
            <li class="<?php echo $i % 2 ? 'odd' : 'even' ?>">
                <div>
                    <code><?php echo $query['sql'] ?></code>
                </div>
                <small>
                    <?php //echo $query['params'] ?>
                    <strong>Time</strong>: <?php echo sprintf('%0.2f', $query['executionMS']) ?> ms
                </small>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
