<?php

declare(strict_types = 1);

namespace Tests;

use MC\Google\Visualization;
use MC\Google\Visualization_Error;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ExampleTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        // cf. https://stackoverflow.com/a/46390357/377645
        ini_set('precision', '15');
        ini_set('serialize_precision', '-1');
    }

    /**
     * @throws Visualization_Error
     */
    public function testQueryComplete()
    {
        $db = new PDO('sqlite:'.__DIR__.'/../examples/example.db');
        $vis = new Visualization($db);
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

        $parameters = [
            'tq' => 'select country, year, birth_control, infant_mort where birth_control!=0 AND infant_mort!=0 group by country, year label country "Country", year "Année", birth_control "Birth Control Penetration", gdp_us "Per-capita GDP (US Dollars)", savings_rate "Savings Rate", investment_rate "Investment Rate", infant_mort "Infant Mortality", life_expect "Life Expectancy" format year "%d"',
            'tqx' => 'reqId:1',
        ];

        $output = $vis->handleRequest(false, $parameters);

        //file_put_contents(__DIR__.'/result1.js', $output);
        self::assertStringEqualsFile(__DIR__.'/result1.js', $output);
    }

    /**
     * @throws Visualization_Error
     */
    public function testQuerySimple()
    {
        $db = new PDO('sqlite:'.__DIR__.'/../examples/example.db');
        $vis = new Visualization($db);
        $vis->addEntity('countries', [
            'fields' => [
                'id' => ['field' => 'id', 'type' => 'number'],
                'name' => ['field' => 'name', 'type' => 'text'],
            ],
        ]);

        $parameters = [
            'tq' => 'select id, name from countries order by name label id "ID", name "Name"',
            'tqx' => 'reqId:2',
        ];

        $output = $vis->handleRequest(false, $parameters);

        //file_put_contents(__DIR__.'/result2.js', $output);
        self::assertStringEqualsFile(__DIR__.'/result2.js', $output);
    }

    /**
     * @throws Visualization_Error
     */
    public function testQueryJoins()
    {
        $db = new PDO('sqlite:'.__DIR__.'/../examples/example.db');
        $vis = new Visualization($db);
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

        $parameters = [
            'tq' => 'select avg(life_male), avg(life_female), avg(life_both) from countries label life_male "Life Expectancy (Male)", life_female "Life Expectancy (Female)", life_both "Life Expectancy (Combined)" format life_male "%.2f years", life_female "%.2f years", life_both "%.2f years"',
            'tqx' => 'reqId:3',
        ];

        $output = $vis->handleRequest(false, $parameters);

        //file_put_contents(__DIR__.'/result3.js', $output);
        self::assertStringEqualsFile(__DIR__.'/result3.js', $output);
    }
}
