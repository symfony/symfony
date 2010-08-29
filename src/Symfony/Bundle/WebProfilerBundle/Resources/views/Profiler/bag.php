<table>
    <tr>
        <th>Key</th>
        <th>Value</th>
    </tr>
    <?php foreach ($bag->keys() as $key): ?>
        <tr>
            <th><?php echo $key ?></th>
            <td><?php echo $bag->get($key) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
