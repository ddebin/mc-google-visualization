<?php
require_once 'init.php';

if(isset($_GET['tq'])) {
    $vis->addEntity('countries', array(
        'table' => 'countries c',
        'fields' => array(
            'id' => array('field' => 'id', 'type' => 'number'),
            'name' => array('field' => 'name', 'type' => 'text'),
            'life_male' => array('field' => 'm.life_male', 'type' => 'number', 'join' => 'mort'),
            'life_female' => array('field' => 'm.life_female', 'type' => 'number', 'join' => 'mort'),
            'life_both' => array('field' => 'm.life_both', 'type' => 'number', 'join' => 'mort'),
            'gdp_us' => array('field' => 'f.gdp_us', 'type' => 'number', 'join' => 'finance'),
            'gdp_year' => array('field' => 'f.year', 'type' => 'text', 'join' => 'finance')
        ),
        'joins' => array(
            'mort' => 'INNER JOIN mortality m ON m.country_id=c.id',
            'finance' => 'INNER JOIN finance f ON f.country_id=c.id'
        )
    ));
    
    $vis->handleRequest();
    die();
}
?>
<html>
<head>
    <title>Joins and aggregate functions visualization example</title>
    <script type="text/javascript" src="http://www.google.com/jsapi"></script>
    <script type="text/javascript">
        google.load('visualization', '1', {'packages': ['columnchart', 'linechart']});
        google.setOnLoadCallback(function() {
            var query = new google.visualization.Query('joins.php');
            query.setQuery('select avg(life_male), avg(life_female), avg(life_both) from countries label life_male "Life Expectancy (Male)", life_female "Life Expectancy (Female)", life_both "Life Expectancy (Combined)" format life_male "%.2f years", life_female "%.2f years", life_both "%.2f years"');
            query.send(function(res) {
                if(res.isError()) {
                    alert(res.getDetailedMessage());
                } else {
                    var table = new google.visualization.ColumnChart(document.getElementById('chart-div'));
                    table.draw(res.getDataTable(), {'height': 500});
                }

                var query2 = new google.visualization.Query('joins.php');
                query.setQuery('select gdp_year, sum(gdp_us) from countries group by gdp_year label gdp_us "Per-capita GDP (US Dollars)"');
                query.send(function(res) {
                    if(res.isError()) {
                        alert(res.getDetailedMessage());
                    } else {
                        var table = new google.visualization.LineChart(document.getElementById('chart2-div'));
                        table.draw(res.getDataTable(), {'height': 400});
                    }
                });
            });
            
        });
    </script>
</head>
<body>
    <p>Entities can also include a set of related tables, where certain fields can rely on database
    joins.  When those fields are selected, the required joins are automatically added to the SQL query.</p>
    <div id="chart-div"></div>
    <div id="chart2-div"></div>
</body>
</html>
