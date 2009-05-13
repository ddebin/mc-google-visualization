<?php
require_once 'init.php';
require_once 'MC/Google/Visualization.php';

class VisualizationTest extends PHPUnit_Framework_TestCase {
    public function testGenSQL() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('entity', array(
            'table' => 'table',
            'fields' => array(
                'id' => array(
                    'field' => 'unique_id',
                    'type' => 'text'
                ),
                'some' => array(
                    'field' => 'some_number',
                    'type' => 'number'
                )
            )
        ));

        $vis->addEntity('entity2', array(
            'fields' => array(
                'id' => array('field' => 'unique_id', 'type' => 'text'),
                'name' => array('field' => 'name', 'type' => 'text'),
                'created_year' => array('field' => 'YEAR(date_created)', 'type' => 'number'),
                'company' => array('field' => 'e3.company', 'join' => 'entity3', 'type' => 'text')
            ),
            'joins' => array(
                'entity3' => 'INNER JOIN entity3 e3 USING (id)'
            )
        ));

        $vis->setDefaultEntity('entity');

        $this->assertEquals('SELECT unique_id AS id, some_number AS some FROM table', $vis->getSQL('select *'));
        $this->assertEquals('SELECT unique_id AS id, some_number AS some FROM table', $vis->getSQL('select id, some from entity'));

        $this->assertEquals('SELECT e3.company AS company FROM entity2 INNER JOIN entity3 e3 USING (id)', $vis->getSQL('select company from entity2'));

        $this->assertEquals('SELECT unique_id AS id, name AS name, YEAR(date_created) AS created_year FROM entity2', $vis->getSQL('select id, name, created_year from entity2'));
    }

    public function testFunctions() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('campaigns', array(
            'fields' => array(
                'id' => array('field' => 'unique_id', 'type' => 'text'),
                'opens' => array('field' => 'opens', 'type' => 'number')
            )
        ));

        $sql = $vis->getSQL('select max(opens) from campaigns');
        $this->assertEquals('SELECT MAX(opens) AS `max-opens` FROM campaigns', $sql);
    }

    public function testSerializeFunction() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('campaigns', array(
            'fields' => array(
                'id' => array('field' => 'unique_id', 'type' => 'text'),
                'opens' => array('field' => 'opens', 'type' => 'number')
            )
        ));

        $query = $vis->parseQuery('select max(opens) from campaigns');
        $meta = $vis->generateMetadata($query);
        $val = $vis->getRowValues(array('max-opens' => 10), $meta);
        $this->assertEquals('{c:[{v:10,f:"10"}]}', $val);
    }

    public function testWhere() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('campaigns', array(
            'fields' => array(
                'title' => array('field' => 'title', 'type' => 'text'),
                'sent' => array('field' => 'emails_sent', 'type' => 'number'),
                'status' => array('field' => 'status', 'type' => 'text')
            )
        ));

        $this->assertEquals('SELECT title AS title FROM campaigns WHERE (( status = "sent" ))', $vis->getSQL('select title from campaigns where (status = "sent")'));
    }

    public function testCallback() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('campaigns', array(
            'fields' => array(
                'field' => array('field' => 'campaign_id', 'type' => 'number'),
                'req_callback' => array(
                    'fields' => array('field'),
                    'type' => 'text',
                    'callback' => array('VisualizationTest', 'callback')
                )
            )
        ));

        $this->assertEquals('SELECT campaign_id AS field FROM campaigns', $vis->getSQL('select req_callback from campaigns'));

        $query = $vis->parseQuery('select req_callback from campaigns');
        $meta = $vis->generateMetadata($query);
        $this->assertEquals('{c:[{v:"callback-1"}]}', $vis->getRowValues(array('field' => '1'), $meta));
    }

    public function testOrderBy() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('campaigns', array(
            'fields' => array(
                'id' => array('field' => 'campaign_id', 'type' => 'number')
            )
        ));

        $this->assertEquals('SELECT campaign_id AS id FROM campaigns ORDER BY campaign_id ASC', $vis->getSQL('select id from campaigns order by id'));
    }

    public function testFormat() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('campaigns', array(
                'fields' => array(
                    'date_field' => array('field' => 'date_field', 'type' => 'date'),
                    'num_field' => array('field' => 'num_field', 'type' => 'number'),
                    'bool_field' => array('field' => 'bool_field', 'type' => 'boolean')
                )
        ));

        $query = $vis->parseQuery('select * from campaigns format date_field "m/d/Y", num_field "num:2", bool_field "N:Y"');
        $meta = $vis->generateMetadata($query);
        $val = $vis->getRowValues(array('date_field' => '2007-01-01', 'num_field' => '2057.566', 'bool_field' => 0), $meta);
        $this->assertEquals('{c:[{v:new Date(2007,0,1),f:"01\/01\/2007"},{v:2057.566,f:"2,057.57"},{v:false,f:"N"}]}', $val);
    }

    public function testGroupBy() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('campaigns', array(
                'fields' => array(
                    'num_field' => array('field' => 'num_field', 'type' => 'number'),
                    'group_field' => array('field' => 'sub_field', 'type' => 'text')
                )
        ));

        $this->assertEquals('SELECT sub_field AS group_field, SUM(num_field) AS `sum-num_field` FROM campaigns GROUP BY sub_field', $vis->getSQL('select group_field, sum(num_field) from campaigns group by group_field'));
    }

    public function testPivot() {
        if(!in_array('sqlite', PDO::getAvailableDrivers())) {
            $this->markTestSkipped('Pivot tests require the SQLite PDO drivers to be installed');
        }
        
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $vis = new MC_Google_Visualization($db);
        $vis->addEntity('users', array(
                'fields' => array(
                    'id' => array('field' => 'unique_id', 'type' => 'number'),
                    'username' => array('field' => 'username', 'type' => 'text')
                )
        ));
        
        $db->exec('CREATE TABLE users (unique_id, username);');
        $db->exec('INSERT INTO users VALUES (1, "user1");');
        $db->exec('INSERT INTO users VALUES (2, "user2");');

        $sql = $vis->getSQL('select count(id) from users pivot username');
        $this->assertEquals('SELECT COUNT(CASE WHEN username=\'user1\' THEN unique_id ELSE NULL END) AS `user1 count-id`, COUNT(CASE WHEN username=\'user2\' THEN unique_id ELSE NULL END) AS `user2 count-id` FROM users', $sql);
    }

    /**
     * Regression
     */
    public function testLabelAfterWhereAndGroupBy() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('orders', array(
                'fields' => array('amount' => array('field' => 'amount', 'type' => 'number'))
        ));

        $this->assertEquals('SELECT SUM(amount) AS `sum-amount` FROM orders WHERE (amount = true) GROUP BY amount', $vis->getSQL('select sum(amount) from orders where amount=true group by amount label amount "Revenue"'));
    }
    
    public function testDateLiterals() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('orders', array(
            'fields' => array('order_date' => array('field' => 'order_date', 'type' => 'datetime'))
        ));
        $this->assertEquals("SELECT order_date AS order_date FROM orders WHERE (order_date > '2008-01-01 00:00:00')", $vis->getSQL('select order_date from orders where order_date > date "2008-01-01 00:00:00"'));
    }
    
    public function testNoFormat() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('orders', array(
            'fields' => array('amount' => array('field' => 'amount', 'type' => 'number'))
        ));
        
        $query = $vis->parseQuery('select amount from orders options no_format');
        $meta = $vis->generateMetadata($query);
        $val = $vis->getRowValues(array('amount' => 10000), $meta);
        $this->assertEquals('{c:[{v:10000}]}', $val);
    }
    
    public function testNoValues() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('orders', array(
            'fields' => array('amount' => array('field' => 'amount', 'type' => 'number'))
        ));
        
        $query = $vis->parseQuery('select amount from orders options no_values');
        $meta = $vis->generateMetadata($query);
        $val = $vis->getRowValues(array('amount' => 10000), $meta);
        $this->assertEquals('{c:[{f:"10,000"}]}', $val);
    }
    
    public function testCountNonNumber() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('orders', array(
            'fields' => array(
                'product' => array('field' => 'product', 'type' => 'text'),
                'id' => array('field' => 'id', 'type' => 'text')
        )));
        $query = $vis->parseQuery('select count(id), product from orders group by product');
        $meta = $vis->generateMetadata($query);
        $val = $vis->getRowValues(array('count-id' => 2, 'product' => 'test product'), $meta);
        $this->assertEquals('{c:[{v:2,f:"2"},{v:"test product"}]}', $val);
    }
    
    public function testIsNull() {
        $vis = new MC_Google_Visualization();
        $vis->addEntity('orders', array(
            'fields' => array(
                'product' => array('field' => 'product', 'type' => 'text'),
                'id' => array('field' => 'id', 'type' => 'text')
        )));
        $sql = $vis->getSQL('select id from orders where product is not null');
        $this->assertEquals('SELECT id AS id FROM orders WHERE (product IS NOT NULL)', $sql);
    }

    public static function callback($row) {
        return 'callback-' . $row['field'];
    }
}

?>
