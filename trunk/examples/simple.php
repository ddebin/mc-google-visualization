<?php
require_once 'init.php';

if(isset($_GET['tq'])) {
    $vis->addEntity('countries', array(
        'fields' => array(
            'id' => array('field' => 'id', 'type' => 'number'),
            'name' => array('field' => 'name', 'type' => 'text')
        )
    ));
    
    $vis->handleRequest();
    die();
}
?>
<html>
<head>
    <title>Simple single-table visualization example</title>
    <script type="text/javascript" src="http://www.google.com/jsapi"></script>
    <script type="text/javascript">
        google.load('visualization', '1', {'packages': ['table']});
        google.setOnLoadCallback(function() {
            var query = new google.visualization.Query('simple.php');
            query.setQuery('select id, name from countries order by name label id "ID", name "Name"');
            query.send(function(res) {
                if(res.isError()) {
                    alert(res.getDetailedMessage());
                } else {
                    var table = new google.visualization.Table(document.getElementById('table-div'));
                    table.draw(res.getDataTable(), {'page': 'enable', 'pageSize': 20});
                }
            });
        });
    </script>
</head>
<body>
    <p>This simple example shows how to define an entity that can be selected on, then using the query
    language to display results in a table.</p>
    <div id="table-div"></div>
</body>
</html>
