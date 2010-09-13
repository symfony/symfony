<h2>Queries</h2>

<?php if (false === $queries = $data->getQueries()): ?>
    <em>Query logging is disabled.</em>
<?php elseif (0 == $data->getQueryCount()): ?>
    <em>No queries.</em>
<?php else: ?>
    <ul class="alt">
        <?php foreach ($queries as $i => $query): ?>
            <li class="<?php echo $i % 2 ? 'odd' : 'even' ?>">
                <div>
                    <code><?php echo $query ?></code>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
