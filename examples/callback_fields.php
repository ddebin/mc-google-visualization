<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types = 1);

use MC\Google\Visualization;

require_once __DIR__.'/../vendor/autoload.php';

function most_common(array $row): ?string
{
    $forms = ['pill', 'iud', 'condom', 'sterile_total', 'other_modern', 'traditional'];
    $maxForm = -1;
    $formName = null;
    foreach ($forms as $form) {
        if ($row[$form] > $maxForm) {
            $maxForm = $row[$form];
            $formName = $form;
        }
    }

    return $formName;
}

$db = new PDO('sqlite:example.db');

$vis = new Visualization($db);

if (isset($_GET['tq'])) {
    $vis->addEntity('birth_control', [
        'fields' => [
            'country' => ['field' => 'c.name', 'type' => 'text', 'join' => 'country'],
            'year' => ['field' => 'year', 'type' => 'number'],
            'pill' => ['field' => 'pill', 'type' => 'number'],
            'iud' => ['field' => 'iud', 'type' => 'number'],
            'condom' => ['field' => 'condom', 'type' => 'number'],
            'sterile_total' => ['field' => 'sterile_total', 'type' => 'number'],
            'other_modern' => ['field' => 'other_modern', 'type' => 'number'],
            'traditional' => ['field' => 'traditional', 'type' => 'number'],
            'most_common' => [
                'callback' => 'most_common',
                'fields' => ['pill', 'iud', 'condom', 'sterile_total', 'other_modern', 'traditional'],
                'type' => 'text', ],
        ],
        'joins' => ['country' => 'INNER JOIN countries c ON c.id=country_id'],
        'where' => 'year=2000',
    ]);

    $vis->handleRequest();
    exit;
}
?>
<html lang="en">
<head>
    <title>Callback fields visualization example</title>
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">
        google.load('visualization', '1', {'packages': ['table']});
        google.setOnLoadCallback(function() {
            const query = new google.visualization.Query('callback_fields.php');
            query.setQuery('select country, most_common from birth_control order by country label country "Country", most_common "Most Common Method"');
            query.send(function(res) {
                if(res.isError()) {
                    alert(res.getDetailedMessage());
                } else {
                    const table = new google.visualization.Table(document.getElementById('table-div'));
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
