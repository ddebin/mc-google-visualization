<?php
require_once 'init.php';

if(isset($_GET['tq'])) {
    
    function most_common($row) {
        $forms = array('pill', 'iud', 'condom', 'sterile_total', 'other_modern', 'traditional');
        $max_form = -1;
        $form_name = null;
        foreach($forms as $form) {
            if($row[$form] > $max_form) {
                $max_form = $row[$form];
                $form_name = $form;
            }
        }
        
        return $form_name;
    }
    
    $vis->addEntity('birth_control', array(
        'fields' => array(
            'country' => array('field' => 'c.name', 'type' => 'text', 'join' => 'country'),
            'year' => array('field' => 'year', 'type' => 'number'),
            'pill' => array('field' => 'pill', 'type' => 'number'),
            'iud' => array('field' => 'iud', 'type' => 'number'),
            'condom' => array('field' => 'condom', 'type' => 'number'),
            'sterile_total' => array('field' => 'steril_total', 'type' => 'number'),
            'other_modern' => array('field' => 'other_modern', 'type' => 'number'),
            'traditional' => array('field' => 'traditional',  'type' => 'number'),
            'most_common' => array(
                'callback' => 'most_common',
                'fields' => array('pill', 'iud', 'condom', 'sterile_total', 'other_modern', 'traditional'),
                'type' => 'text')
        ),
        'joins' => array('country' => 'INNER JOIN countries c ON c.id=country_id'),
        'where' => 'year=2000'
    ));
    
    $vis->handleRequest();
    die();
}
?>
<html>
<head>
    <title>Callback fields visualization example</title>
    <script type="text/javascript" src="http://www.google.com/jsapi"></script>
    <script type="text/javascript">
        google.load('visualization', '1', {'packages': ['table']});
        google.setOnLoadCallback(function() {
            var query = new google.visualization.Query('callback_fields.php');
            query.setQuery('select country, most_common from birth_control order by country label country "Country", most_common "Most Common Method"');
            query.send(function(res) {
                if(res.isError()) {
                    alert(res.getDetailedMessage());
                } else {
                    var table = new google.visualization.Table(document.getElementById('table-div'));
                    table.draw(res.getDataTable());
                }
            });
        });
    </script>
</head>
<body>
    <p>You can also provide fields that are generated using callback functions instead of raw SQL.  These
    fields can depend on one or more other fields defined in the entity.  You cannot filter, group, or pivot
    on callback-based fields.  Normally, you also cannot order on callback fields, but you can delegate ordering
    to a separate field on the entity by providing a "sort_field" setting.</p>
    <div id="table-div"></div>
</body>
</html>
