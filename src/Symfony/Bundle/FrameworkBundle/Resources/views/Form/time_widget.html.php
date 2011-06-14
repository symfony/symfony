<?php if ($widget == 'single_text'): ?>
    <input type="text"
        <?php echo $view['form']->renderBlock('attributes') ?>
        name="<?php echo $view->escape($full_name) ?>"
        value="<?php echo $view->escape($value) ?>"
        <?php if ($read_only): ?>disabled="disabled"<?php endif ?>
        <?php if ($required): ?>required="required"<?php endif ?>
        <?php if ($max_length): ?>maxlength="<?php echo $max_length ?>"<?php endif ?>
    />
<?php else: ?>
    <div<?php echo $view['form']->renderBlock('container_attributes') ?>>
        <?php
            // There should be no spaces between the colons and the widgets, that's why
            // this block is written in a single PHP tag
            echo $view['form']->widget($form['hour'], array('attr' => array('size' => 1)));
            echo ':';
            echo $view['form']->widget($form['minute'], array('attr' => array('size' => 1)));

            if ($with_seconds) {
                echo ':';
                echo $view['form']->widget($form['second'], array('attr' => array('size' => 1)));
            }
        ?>
    </div>
<?php endif ?>
