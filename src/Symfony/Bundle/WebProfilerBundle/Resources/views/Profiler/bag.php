<table>
    <tr>
        <th>Key</th>
        <th>Value</th>
    </tr>
    <?php foreach ($bag->keys() as $key): ?>
        <?php echo $view->render('WebProfilerBundle:Profiler:var_dump.php', array('key' => $key, 'value' => $bag->get($key))) ?>
    <?php endforeach; ?>
</table>
