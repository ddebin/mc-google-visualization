<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types = 1);

use MC\Google\Visualization;

require_once __DIR__.'/../vendor/autoload.php';

$db = new PDO('sqlite:example.db');

$vis = new Visualization($db);

if (isset($_GET['tq'])) {
    $vis->addEntity('countries', [
        'table' => 'countries c',
        'fields' => [
            'id' => ['field' => 'id', 'type' => 'number'],
            'name' => ['field' => 'name', 'type' => 'text'],
            'life_male' => ['field' => 'm.life_male', 'type' => 'number', 'join' => 'mort'],
            'life_female' => ['field' => 'm.life_female', 'type' => 'number', 'join' => 'mort'],
            'life_both' => ['field' => 'm.life_both', 'type' => 'number', 'join' => 'mort'],
            'gdp_us' => ['field' => 'f.gdp_us', 'type' => 'number', 'join' => 'finance'],
            'gdp_year' => ['field' => 'f.year', 'type' => 'text', 'join' => 'finance'],
        ],
        'joins' => [
            'mort' => 'INNER JOIN mortality m ON m.country_id=c.id',
            'finance' => 'INNER JOIN finance f ON f.country_id=c.id',
        ],
    ]);

    $vis->handleRequest();
    exit;
}
?>
<html lang="en">
<head>
    <title>Joins and aggregate functions visualization example</title>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', {'packages': ['columnchart', 'linechart']});
        window.addEventListener('DOMContentLoaded', function() { google.charts.setOnLoadCallback(function() {
            const query = new google.visualization.Query('joins.php');
            query.setQuery('select avg(life_male), avg(life_female), avg(life_both) from countries label life_male "Life Expectancy (Male)", life_female "Life Expectancy (Female)", life_both "Life Expectancy (Combined)" format life_male "%.2f years", life_female "%.2f years", life_both "%.2f years"');
            query.send(function(res) {
                if(res.isError()) {
                    alert(res.getDetailedMessage());
                } else {
                    const table = new google.visualization.ColumnChart(document.getElementById('chart-div'));
                    table.draw(res.getDataTable(), {'height': 500});
                }

                const query2 = new google.visualization.Query('joins.php');
                query2.setQuery('select gdp_year, sum(gdp_us) from countries group by gdp_year label gdp_us "Per-capita GDP (US Dollars)"');
                query2.send(function(res) {
                    if(res.isError()) {
                        alert(res.getDetailedMessage());
                    } else {
                        const table = new google.visualization.LineChart(document.getElementById('chart2-div'));
                        table.draw(res.getDataTable(), {'height': 400});
                    }
                });
            });
        }); });
    </script>
</head>
<body>
    <p>Entities can also include a set of related tables, where certain fields can rely on database
    joins.  When those fields are selected, the required joins are automatically added to the SQL query.</p>
    <div id="chart-div"></div>
    <div id="chart2-div"></div>
</body>
</html>
