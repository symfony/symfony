<tr id="<?php echo $view->escape($id) ?>_container" style="display: none;">
    <td>
        <table>
            <?php echo $view['form']->row($form) ?>
        </table>
    </td>
    <td>
        <?php // using colspan="2" would cause html5 validation to fail when the prototype is the only child ?>
    </td>
</tr>