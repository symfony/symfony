<?php if (is_object($value) && $value instanceof Symfony\Component\OutputEscaper\BaseEscaper): ?>
    <?php $value = $value->getRawValue() ?>
<?php endif; ?>

<tr>
    <th><?php echo $key ?></th>
    <td>
        <?php if (is_resource($value)): ?>
            <em>Resource</em>
        <?php elseif (is_array($value) || is_object($value)): ?>
            <em><?php echo ucfirst(gettype($value)) ?></em>
            <?php echo Symfony\Component\Yaml\Inline::dump($value) ?>
        <?php else: ?>
            <?php echo $value ?>
        <?php endif; ?>
    </td>
</tr>
