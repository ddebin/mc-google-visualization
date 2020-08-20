<?php

namespace Tests;

use MC\Parser;
use MC\Parser\DefError;
use MC\Parser\ParseError;
use MC\Parser\Token;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ParserTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        // cf. https://stackoverflow.com/a/46390357/377645
        ini_set('precision', '15');
        ini_set('serialize_precision', '-1');
    }

    /**
     * @throws ParseError
     */
    public function testLiteral()
    {
        $p = new Parser();
        $select = $p->keyword('select', true);

        $select->parse('sELECT');
        static::assertSame(['select'], $select->parse('select    ')->getValues());

        $from = $p->keyword('from', true);
        $selectfrom = $p->set($select, $from);
        static::assertSame(['select', 'from'], $selectfrom->parse('select  from   ')->getValues());
    }

    public function testName()
    {
        $p = new Parser();
        $select = $p->keyword('select');

        static::assertSame('select', $select->getName());
        $select->name('SELECT');
        static::assertSame('SELECT', $select->getName());
    }

    public function testToken()
    {
        $p = new Parser();
        $select = $p->keyword('select');

        $token = $select->token('test');
        assert($token !== null);
        static::assertInstanceOf(Token::class, $token);
        static::assertSame([[null, 'test']], $token->getNameValues());
        static::assertSame([], $token->getChildren());
    }

    public function testTokenGroup()
    {
        $p = new Parser();
        $select = $p->keyword('select');

        $group = $select->tokenGroup();
        static::assertSame([], $group->getNameValues());
        static::assertSame([], $group->getChildren());

        $group->append(null);
        static::assertSame([], $group->getChildren());
    }

    /**
     * @throws ParseError
     */
    public function testOneOf()
    {
        $p = new Parser();
        $select = $p->keyword('select');
        $from = $p->keyword('from');
        $oneof = $p->oneOf($select, $from);
        static::assertSame(['select'], $oneof->parse('select')->getValues());
        static::assertSame(['from'], $oneof->parse('from')->getValues());
    }

    /**
     * @throws ParseError
     */
    public function testOptional()
    {
        $p = new Parser();
        $select = $p->keyword('select');
        $from = $p->keyword('from');
        $query = $p->set($p->optional($select), $p->optional($from));
        static::assertSame([], $query->parse('')->getValues());
        static::assertSame(['select'], $query->parse('select')->getValues());
        static::assertSame(['from'], $query->parse('from')->getValues());
        static::assertSame(['select', 'from'], $query->parse('select from')->getValues());
    }

    /**
     * @throws ParseError
     */
    public function testWord()
    {
        $p = new Parser();
        $select = $p->keyword('select');
        $word = $p->word($p->alphas(), $p->alphanums());
        $query = $p->set($select, $word);
        static::assertSame(['select', 'a90'], $query->parse('select a90  ')->getValues());
    }

    /**
     * @throws ParseError
     */
    public function testDelimitedList()
    {
        $p = new Parser();
        $select = $p->keyword('select');
        $word = $p->word($p->alphas());
        $query = $p->set($select, $p->delimitedList($word));
        static::assertSame(['select', 'one', 'two'], $query->parse('select one,two')->getValues());
    }

    /**
     * @throws ParseError
     */
    public function testRecursive()
    {
        $p = new Parser();
        $expr = $p->recursive();
        $expr->replace($p->set($p->literal('{'), $p->optional($expr), $p->literal('}')));

        static::assertSame(['{', '{', '}', '}'], $expr->parse('{{ }}')->getValues());
    }

    /**
     * @throws DefError
     * @throws ParseError
     */
    public function testDelimitedSet()
    {
        $p = new Parser();
        $limit = $p->set($p->keyword('limit'), $p->delimitedList($p->set($p->word($p->alphanums()), $p->quotedString())));
        static::assertSame(['limit', 'field', '"label"', 'field2', '"label2"'], $limit->parse('limit field "label", field2 "label2"')->getValues());
    }

    /**
     * @throws DefError
     * @throws ParseError
     */
    public function testVisParser()
    {
        $p = new Parser();
        $ident = $p->oneOf(
            $p->word($p->alphas().'_', $p->alphanums().'_'),
            $p->quotedString('`')
        );

        $literal = $p->oneOf(
            $p->number()->name('number'),
            $p->quotedString()->name('string'),
            $p->boolean('lower')->name('boolean'),
            $p->set($p->keyword('date', true), $p->quotedString())->name('date'),
            $p->set($p->keyword('timeofday', true), $p->quotedString())->name('time'),
            $p->set(
                $p->oneOf(
                    $p->keyword('datetime', true),
                    $p->keyword('timestamp', true)
                ),
                $p->quotedString()
            )->name('datetime')
        );

        $function = $p->set($p->oneOf($p->literal('min', true), $p->literal('max', true), $p->literal('count', true), $p->literal('avg', true), $p->literal('sum', true))->name('func_name'), $p->literal('(')->suppress(), $ident, $p->literal(')')->suppress())->name('function');

        $select = $p->set($p->keyword('select', true), $p->oneOf($p->keyword('*'), $p->delimitedList($p->oneOf($function, $ident))))->name('select');
        $from = $p->set($p->keyword('from', true), $ident)->name('from');

        $comparison = $p->oneOf($p->literal('<'), $p->literal('<='), $p->literal('>'), $p->literal('>='), $p->literal('='), $p->literal('!='), $p->literal('<>'))->name('operator');

        $expr = $p->recursive();
        $value = $p->oneOf($ident, $literal);
        $cond = $p->oneOf(
            $p->set($value, $comparison, $value),
            $p->set($value, $p->keyword('is', true), $p->keyword('null', true)),
            $p->set($value, $p->keyword('is', true), $p->keyword('not', true), $p->keyword('null', true)),
            $p->set($p->literal('('), $expr, $p->literal(')'))
        );

        $andor = $p->oneOf($p->keyword('and', true), $p->keyword('or', true));

        $expr->replace($p->set($cond, $p->zeroOrMore($p->set($andor, $expr))))->name('where_expr');

        $where = $p->set($p->keyword('where', true), $expr)->name('where');

        $groupby = $p->set($p->keyword('group', true), $p->keyword('by', true), $p->delimitedList($ident));
        $pivot = $p->set($p->keyword('pivot', true), $p->delimitedList($ident));
        $limit = $p->set($p->keyword('limit', true), $p->word($p->nums()))->name('limit');
        $offset = $p->set($p->keyword('offset', true), $p->word($p->nums()))->name('offset');
        $label = $p->set($p->keyword('label', true), $p->delimitedList($p->set($ident, $p->quotedString())));
        $format = $p->set($p->keyword('format', true), $p->delimitedList($p->set($ident, $p->quotedString())));
        $options = $p->set($p->keyword('options', true), $p->delimitedList($p->word($p->alphas().'_')));

        $query = $p->set($p->optional($select), $p->optional($from), $p->optional($where), $p->optional($groupby), $p->optional($pivot), $p->optional($limit), $p->optional($offset), $p->optional($label), $p->optional($format), $p->optional($options));

        $result = $query->parse('');
        static::assertSame([], $result->getValues(), 'empty query');

        $result = $query->parse('select *');
        static::assertSame(['select', '*'], $result->getValues(), 'simple select *');

        $result = $query->parse('from something');
        static::assertSame(['from', 'something'], $result->getValues(), 'select-less from');

        $result = $query->parse('select one, two from table');
        static::assertSame(['select', 'one', 'two', 'from', 'table'], $result->getValues(), 'select list');

        $result = $query->parse('select max(one) from table where (two>1.5 and (three < 4)) group by one');
        static::assertSame(['select', 'max', 'one', 'from', 'table', 'where', '(', 'two', '>', '1.5', 'and', '(', 'three', '<', '4', ')', ')', 'group', 'by', 'one'], $result->getValues());
    }

    /**
     * @throws DefError
     */
    public function testQuotedStringEx1()
    {
        $p = new Parser();
        $this->expectException(DefError::class);
        $p->quotedString(' ');
    }

    /**
     * @throws DefError
     */
    public function testQuotedStringEx2()
    {
        $p = new Parser();
        $this->expectException(DefError::class);
        $p->quotedString('test', 'error');
    }
}
