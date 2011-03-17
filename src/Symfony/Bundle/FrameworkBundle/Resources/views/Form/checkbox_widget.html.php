<input type="checkbox"
    id="<?php echo $id; ?>"
    name="<?php echo $name ?>"
    <?php if ($value): ?>value="<?php echo $value ?>"<?php endif ?>
    <?php if ($disabled): ?>disabled="disabled"<?php endif ?>
    <?php if ($required): ?>required="required"<?php endif ?>
    <?php if ($checked): ?>checked="checked"<?php endif ?>
/>
