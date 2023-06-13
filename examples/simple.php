<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types = 1);

use MC\Google\Visualization;

require_once __DIR__.'/../vendor/autoload.php';

$db = new PDO('sqlite:example.db');

$vis = new Visualization($db);

if (isset($_GET['tq'])) {
    $vis->addEntity('countries', [
        'fields' => [
            'id' => ['field' => 'id', 'type' => 'number'],
            'name' => ['field' => 'name', 'type' => 'text'],
        ],
    ]);

    $vis->handleRequest();
    exit;
}
?>
<html lang="en">
<head>
    <title>Simple single-table visualization example</title>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', {'packages': ['table']});
        window.addEventListener('DOMContentLoaded', function() { google.charts.setOnLoadCallback(function() {
            const query = new google.visualization.Query('simple.php');
            query.setQuery('select id, name from countries order by name label id "ID", name "Name"');
            query.send(function(res) {
                if(res.isError()) {
                    alert(res.getDetailedMessage());
                } else {
                    const table = new google.visualization.Table(document.getElementById('table-div'));
                    table.draw(res.getDataTable(), {'page': 'enable', 'pageSize': 20});
                }
            });
        }); });
    </script>
</head>
<body>
    <p>This simple example shows how to define an entity that can be selected on, then using the query
    language to display results in a table.</p>
    <div id="table-div"></div>
</body>
</html>
