<div class="import clearfix">
    <h3>
        <img style="margin: 0 5px 0 0; vertical-align: middle; height: 16px" alt="" src="<?php echo $view->get('assets')->getUrl('bundles/webprofiler/images/import.png') ?>" />
        Admin
    </h3>

    <form action="<?php echo $view->get('router')->generate('_profiler_import') ?>" method="post" enctype="multipart/form-data">
        <div style="margin-bottom: 10px">
            &raquo;&nbsp;<a href="<?php echo $view->get('router')->generate('_profiler_purge', array('token' => $token)) ?>">Purge</a>
        </div>
        <div style="margin-bottom: 10px">
            &raquo;&nbsp;<a href="<?php echo $view->get('router')->generate('_profiler_export', array('token' => $token)) ?>">Export</a>
        </div>
        &raquo;&nbsp;<label for="file">Import</label><br />
        <input type="file" name="file" id="file" /><br />
        <input class="submit" type="submit" value="upload" />
    </form>
</div>
