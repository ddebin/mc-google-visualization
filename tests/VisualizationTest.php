<?php

declare(strict_types = 1);

namespace Tests;

use MC\Google\Visualization;
use MC\Google\Visualization_Error;
use MC\Google\Visualization_QueryError;
use MC\Parser\DefError;
use MC\Parser\ParseError;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class VisualizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // cf. https://stackoverflow.com/a/46390357/377645
        ini_set('precision', '15');
        ini_set('serialize_precision', '-1');
    }

    /**
     * @throws Visualization_Error
     * @throws ParseError
     * @throws DefError
     */
    public function testGenSQL(): void
    {
        $vis = new Visualization();
        $vis->addEntity('entity', [
            'table' => 'table',
            'fields' => [
                'id' => [
                    'field' => 'unique_id',
                    'type' => 'text',
                ],
                'some' => [
                    'field' => 'some_number',
                    'type' => 'number',
                ],
            ],
        ]);

        $vis->addEntity('entity2', [
            'fields' => [
                'id' => ['field' => 'unique_id', 'type' => 'text'],
                'name' => ['field' => 'name', 'type' => 'text'],
                'created_year' => ['field' => 'YEAR(date_created)', 'type' => 'number'],
                'company' => ['field' => 'e3.company', 'join' => 'entity3', 'type' => 'text'],
            ],
            'joins' => [
                'entity3' => 'INNER JOIN entity3 e3 USING (id)',
            ],
        ]);

        $vis->setDefaultEntity('entity');

        self::assertSame('SELECT unique_id AS id, some_number AS some FROM table', $vis->getSQL('select *'));
        self::assertSame('SELECT unique_id AS id, some_number AS some FROM table', $vis->getSQL('select id, some from entity'));

        self::assertSame('SELECT e3.company AS company FROM entity2 INNER JOIN entity3 e3 USING (id)', $vis->getSQL('select company from entity2'));

        self::assertSame('SELECT unique_id AS id, name AS name, YEAR(date_created) AS created_year FROM entity2', $vis->getSQL('select id, name, created_year from entity2'));
    }

    /**
     * @throws Visualization_Error
     * @throws Visualization_QueryError
     * @throws DefError
     * @throws ParseError
     */
    public function testFunctions(): void
    {
        $vis = new Visualization();
        $vis->addEntity('campaigns', [
            'fields' => [
                'id' => ['field' => 'unique_id', 'type' => 'text'],
                'opens' => ['field' => 'opens', 'type' => 'number'],
            ],
        ]);

        $sql = $vis->getSQL('select max(opens) from campaigns');
        self::assertSame('SELECT MAX(opens) AS `max-opens` FROM campaigns', $sql);
    }

    /**
     * @throws Visualization_Error
     * @throws Visualization_QueryError
     * @throws ParseError
     * @throws DefError
     */
    public function testSerializeFunction(): void
    {
        $vis = new Visualization();
        $vis->addEntity('campaigns', [
            'fields' => [
                'id' => ['field' => 'unique_id', 'type' => 'text'],
                'opens' => ['field' => 'opens', 'type' => 'number'],
            ],
        ]);

        $query = $vis->parseQuery('select max(opens) from campaigns');
        $meta = $vis->generateMetadata($query);
        $val = $vis->getRowValues(['max-opens' => 10], $meta);
        self::assertSame('{c:[{v:10,f:"10"}]}', $val);
    }

    /**
     * @throws Visualization_Error
     * @throws Visualization_QueryError
     * @throws DefError
     * @throws ParseError
     */
    public function testWhere(): void
    {
        $vis = new Visualization();
        $vis->addEntity('campaigns', [
            'fields' => [
                'title' => ['field' => 'title', 'type' => 'text'],
                'sent' => ['field' => 'emails_sent', 'type' => 'number'],
                'status' => ['field' => 'status', 'type' => 'text'],
            ],
        ]);

        self::assertSame('SELECT title AS title FROM campaigns WHERE (( status = "sent" ))', $vis->getSQL('select title from campaigns where (status = "sent")'));
    }

    /**
     * @throws Visualization_Error
     * @throws Visualization_QueryError
     * @throws ParseError
     * @throws DefError
     */
    public function testCallback(): void
    {
        $vis = new Visualization();
        $vis->addEntity('campaigns', [
            'fields' => [
                'field' => ['field' => 'campaign_id', 'type' => 'number'],
                'req_callback' => [
                    'fields' => ['field'],
                    'type' => 'text',
                    'callback' => [__CLASS__, 'callbackTest'],
                ],
            ],
        ]);

        self::assertSame('SELECT campaign_id AS field FROM campaigns', $vis->getSQL('select req_callback from campaigns'));

        $query = $vis->parseQuery('select req_callback from campaigns');
        $meta = $vis->generateMetadata($query);
        self::assertSame('{c:[{v:"callback-1"}]}', $vis->getRowValues(['field' => '1'], $meta));
    }

    /**
     * @throws DefError
     * @throws ParseError
     * @throws Visualization_Error
     * @throws Visualization_QueryError
     */
    public function testOrderBy(): void
    {
        $vis = new Visualization();
        $vis->addEntity('campaigns', [
            'fields' => [
                'id' => ['field' => 'campaign_id', 'type' => 'number'],
            ],
        ]);

        self::assertSame('SELECT campaign_id AS id FROM campaigns ORDER BY campaign_id ASC', $vis->getSQL('select id from campaigns order by id'));
    }

    /**
     * @throws Visualization_Error
     * @throws Visualization_QueryError
     * @throws ParseError
     * @throws DefError
     */
    public function testFormat(): void
    {
        $vis = new Visualization();
        $vis->addEntity('campaigns', [
            'fields' => [
                'date_field' => ['field' => 'date_field', 'type' => 'date'],
                'num_field' => ['field' => 'num_field', 'type' => 'number'],
                'bool_field' => ['field' => 'bool_field', 'type' => 'boolean'],
            ],
        ]);

        $query = $vis->parseQuery('select * from campaigns format date_field "m/d/Y", num_field "num:2", bool_field "N:Y"');
        $meta = $vis->generateMetadata($query);
        $val = $vis->getRowValues(['date_field' => '2007-01-01', 'num_field' => '2057.566', 'bool_field' => 0], $meta);
        self::assertSame('{c:[{v:new Date(2007,0,1),f:"01\/01\/2007"},{v:2057.566,f:"2,057.57"},{v:false,f:"N"}]}', $val);
    }

    /**
     * @throws DefError
     * @throws ParseError
     * @throws Visualization_Error
     * @throws Visualization_QueryError
     */
    public function testGroupBy(): void
    {
        $vis = new Visualization();
        $vis->addEntity('campaigns', [
            'fields' => [
                'num_field' => ['field' => 'num_field', 'type' => 'number'],
                'group_field' => ['field' => 'sub_field', 'type' => 'text'],
            ],
        ]);

        self::assertSame('SELECT sub_field AS group_field, SUM(num_field) AS `sum-num_field` FROM campaigns GROUP BY sub_field', $vis->getSQL('select group_field, sum(num_field) from campaigns group by group_field'));
    }

    /**
     * @throws Visualization_Error
     * @throws ParseError
     * @throws DefError
     */
    public function testPivot(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('Pivot tests require the SQLite PDO drivers to be installed');
        }

        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $vis = new Visualization($db);

        $vis->addEntity('users', [
            'fields' => [
                'id' => ['field' => 'unique_id', 'type' => 'number'],
                'username' => ['field' => 'username', 'type' => 'text'],
            ],
        ]);

        $db->exec('CREATE TABLE users (unique_id, username);');

        $db->exec('INSERT INTO users VALUES (1, "user1");');
        $db->exec('INSERT INTO users VALUES (2, "user2");');

        $sql = $vis->getSQL('select count(id) from users pivot username');
        self::assertSame("SELECT COUNT(CASE WHEN username='user1' THEN unique_id ELSE NULL END) AS `user1 count-id`, COUNT(CASE WHEN username='user2' THEN unique_id ELSE NULL END) AS `user2 count-id` FROM users", $sql);
    }

    /**
     * Regression.
     *
     * @throws Visualization_Error
     * @throws ParseError
     * @throws DefError
     */
    public function testLabelAfterWhereAndGroupBy(): void
    {
        $vis = new Visualization();
        $vis->addEntity('orders', [
            'fields' => ['amount' => ['field' => 'amount', 'type' => 'number']],
        ]);

        self::assertSame('SELECT SUM(amount) AS `sum-amount` FROM orders WHERE (amount = true) GROUP BY amount', $vis->getSQL('select sum(amount) from orders where amount=true group by amount label amount "Revenue"'));
    }

    /**
     * @throws DefError
     * @throws ParseError
     * @throws Visualization_Error
     * @throws Visualization_QueryError
     */
    public function testDateLiterals(): void
    {
        $vis = new Visualization();
        $vis->addEntity('orders', [
            'fields' => ['order_date' => ['field' => 'order_date', 'type' => 'datetime']],
        ]);
        self::assertSame("SELECT order_date AS order_date FROM orders WHERE (order_date > '2008-01-01 00:00:00')", $vis->getSQL('select order_date from orders where order_date > date "2008-01-01 00:00:00"'));
    }

    /**
     * @throws Visualization_Error
     * @throws Visualization_QueryError
     * @throws ParseError
     * @throws DefError
     */
    public function testNoFormat(): void
    {
        $vis = new Visualization();
        $vis->addEntity('orders', [
            'fields' => ['amount' => ['field' => 'amount', 'type' => 'number']],
        ]);

        $query = $vis->parseQuery('select amount from orders options no_format');
        $meta = $vis->generateMetadata($query);
        $val = $vis->getRowValues(['amount' => 10000], $meta);
        self::assertSame('{c:[{v:10000}]}', $val);
    }

    /**
     * @throws Visualization_Error
     * @throws Visualization_QueryError
     * @throws ParseError
     * @throws DefError
     */
    public function testNoValues(): void
    {
        $vis = new Visualization();
        $vis->addEntity('orders', [
            'fields' => ['amount' => ['field' => 'amount', 'type' => 'number']],
        ]);

        $query = $vis->parseQuery('select amount from orders options no_values');
        $meta = $vis->generateMetadata($query);
        $val = $vis->getRowValues(['amount' => 10000], $meta);
        self::assertSame('{c:[{f:"10,000"}]}', $val);
    }

    /**
     * @throws Visualization_Error
     * @throws Visualization_QueryError
     * @throws ParseError
     * @throws DefError
     */
    public function testCountNonNumber(): void
    {
        $vis = new Visualization();
        $vis->addEntity('orders', [
            'fields' => [
                'product' => ['field' => 'product', 'type' => 'text'],
                'id' => ['field' => 'id', 'type' => 'text'],
            ], ]);
        $query = $vis->parseQuery('select count(id), product from orders group by product');
        $meta = $vis->generateMetadata($query);
        $val = $vis->getRowValues(['count-id' => 2, 'product' => 'test product'], $meta);
        self::assertSame('{c:[{v:2,f:"2"},{v:"test product"}]}', $val);
    }

    /**
     * @throws DefError
     * @throws ParseError
     * @throws Visualization_Error
     * @throws Visualization_QueryError
     */
    public function testIsNull(): void
    {
        $vis = new Visualization();
        $vis->addEntity('orders', [
            'fields' => [
                'product' => ['field' => 'product', 'type' => 'text'],
                'id' => ['field' => 'id', 'type' => 'text'],
            ], ]);
        $sql = $vis->getSQL('select id from orders where product is not null');
        self::assertSame('SELECT id AS id FROM orders WHERE (product IS NOT NULL)', $sql);
    }

    public static function callbackTest(array $row): string
    {
        return 'callback-'.$row['field'];
    }
}
