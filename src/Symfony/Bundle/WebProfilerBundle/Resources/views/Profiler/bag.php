<table>
    <tr>
        <th>Key</th>
        <th>Value</th>
    </tr>
    <?php foreach ($bag->keys() as $key): ?>
        <tr>
            <th><?php echo $key ?></th>
            <td>
                <?php if (is_object($bag->get($key))): ?>
                    <em>Object</em>
                <?php elseif (is_resource($bag->get($key))): ?>
                    <em>Resource</em>
                <?php elseif (is_array($bag->get($key))): ?>
                    <em>Array</em>
                <?php else: ?>
                    <?php echo $bag->get($key) ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
