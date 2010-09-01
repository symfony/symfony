<div class="search clearfix">
    <h3>
        <img style="margin: 0 5px 0 0; vertical-align: middle; height: 16px" alt="" src="<?php echo $view->get('assets')->getUrl('bundles/webprofiler/images/search.png') ?>" />
        Search
    </h3>
    <form action="<?php echo $view->get('router')->generate('_profiler_search') ?>" method="get">
        <label for="ip">IP</label>
        <input type="text" name="ip" id="ip" value="<?php echo $ip ?>" />
        <div class="clearfix"></div>
        <label for="url">URL</label>
        <input type="text" name="url" id="url" value="<?php echo $url ?>" />
        <div class="clearfix"></div>
        <label for="token">Token</label>
        <input type="text" name="token" id="token" />
        <div class="clearfix"></div>
        <label for="limit">Limit</label>
        <select name="limit">
            <?php foreach (array(10, 50, 100) as $l): ?>
                <option<?php echo $l == $limit ? ' selected="selected"' : '' ?>><?php echo $l ?></option>
            <?php endforeach; ?>
        </select>
        <input class="submit" type="submit" value="update" /><br />
    </form>
</div>
