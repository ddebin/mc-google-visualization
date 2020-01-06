<?php

/** @noinspection PhpUnhandledExceptionInspection */

use MC\Google\Visualization;

require_once __DIR__.'/../vendor/autoload.php';

$db = new PDO('sqlite:example.db');

$vis = new Visualization($db);

if (isset($_GET['tq'])) {
    $vis->addEntity('timeline', [
        'table' => 'countries c',
        'fields' => [
            'country' => ['field' => 'c.name', 'type' => 'text'],
            'year' => ['field' => 'f.year', 'type' => 'number', 'join' => 'finance'],
            'birth_control' => ['field' => 'b.all_methods', 'type' => 'number', 'join' => 'birth'],
            'gdp_us' => ['field' => 'f.gdp_us', 'type' => 'number', 'join' => 'finance'],
            'savings_rate' => ['field' => 'f.savings_rate', 'type' => 'number', 'join' => 'finance'],
            'investment_rate' => ['field' => 'f.investment_rate', 'type' => 'number', 'join' => 'finance'],
            'infant_mort' => ['field' => 'm.infant_both', 'type' => 'number', 'join' => 'mort'],
            'life_expect' => ['field' => 'm.life_both', 'type' => 'number', 'join' => 'mort'],
        ],
        'joins' => [
            'finance' => 'INNER JOIN finance f ON f.country_id=c.id',
            'birth' => 'INNER JOIN birth_control b ON b.country_id=f.country_id AND b.year=f.year',
            'mort' => 'INNER JOIN mortality m ON m.country_id=f.country_id AND m.year=f.year',
        ],
    ]);
    $vis->setDefaultEntity('timeline');

    $vis->handleRequest();
    die();
}
?>
<html lang="en">
<head>
    <title>Complete visualization example</title>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', {'packages': ['motionchart']});
        window.addEventListener('DOMContentLoaded', function() { google.charts.setOnLoadCallback(loadChart); });
        motion_chart = null;

        function loadChart() {
            var metric1 = document.getElementById('metric-1').value;
            var metric2 = document.getElementById('metric-2').value;
            if (metric1 === metric2) return;

            var str = 'select country, year, ' + metric1 + ', ' + metric2 + ' where ' + metric1 + '!=0 AND ' + metric2 + '!=0 group by country, year label country "Country", year "Year", birth_control "Birth Control Penetration", gdp_us "Per-capita GDP (US Dollars)", savings_rate "Savings Rate", investment_rate "Investment Rate", infant_mort "Infant Mortality", life_expect "Life Expectancy" format year "%d"';

            var query = new google.visualization.Query('complete.php');
            query.setQuery(str);
            query.send(function(res) {
                if (res.isError()) {
                    alert(res.getDetailedMessage());
                } else {
                    if (motion_chart === null) motion_chart = new google.visualization.MotionChart(document.getElementById('chart-div'));
                    motion_chart.draw(res.getDataTable(), {'height': 600, 'width': 800});
                }
            });

        }
    </script>
</head>
<body>
    <p>This is a more complete example showing how you can build queries to provide interesting data visualizations.</p>
    <div class="chart-options">
        Show me
        <select id="metric-1" onchange="loadChart();">
            <option value="birth_control">Birth Control Penetration</option>
            <option value="gdp_us">Per-capita GDP</option>
            <option value="savings_rate">Savings Rate</option>
            <option value="investment_rate">Investment Rate</option>
            <option value="infant_mort">Infant Mortality</option>
            <option value="life_expect">Life Expectancy</option>
        </select>
        against
        <select id="metric-2" onchange="loadChart();">
            <option value="gdp_us">Per-capita GDP</option>
            <option value="birth_control">Birth Control Penetration</option>
            <option value="savings_rate">Savings Rate</option>
            <option value="investment_rate">Investment Rate</option>
            <option value="infant_mort">Infant Mortality</option>
            <option value="life_expect">Life Expectancy</option>
        </select>
    </div>
    <div id="chart-div"></div>
</body>
</html>
