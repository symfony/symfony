<?php $view->extend('WebProfilerBundle:Profiler:layout') ?>

<h2>Search Results</h2>

<table>
    <tr>
        <th>Token</th>
        <th>IP</th>
        <th>URL</th>
        <th>Time</th>
    </tr>
    <?php foreach ($tokens as $elements): ?>
        <tr>
            <td><a href="<?php echo $view->get('router')->generate('_profiler', array('token' => $elements['token'])) ?>"><?php echo $elements['token'] ?></a></td>
            <td><?php echo $elements['ip'] ?></td>
            <td><?php echo $elements['url'] ?></td>
            <td><?php echo date('r', $elements['time']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
