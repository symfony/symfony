<h2>Called Events</h2>

<table>
    <tr>
        <th>Event</th>
        <th>Caller</th>
        <th>Listener</th>
    </tr>
    <?php foreach ($data->getCalledEvents() as $event): ?>
        <tr>
            <td><code><?php echo $event['event'] ?></code></td>
            <td><code><?php echo $view->get('code')->abbrClass($event['caller']) ?></code></td>
            <td><code><?php echo $view->get('code')->abbrMethod($event['listener']) ?>()</code></td>
        </tr>
    <?php endforeach; ?>
</table>

<?php if ($events = $data->getNotCalledEvents()): ?>
    <h2>Not Called Events</h2>

    <table>
        <tr>
            <th>Event</th>
            <th>Listener</th>
        </tr>
        <?php foreach ($events as $event): ?>
            <tr>
                <td><code><?php echo $event['event'] ?></code></td>
                <td><code><?php echo $view->get('code')->abbrMethod($event['listener']) ?>()</code></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
