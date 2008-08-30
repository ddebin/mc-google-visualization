<?php
require_once 'init.php';
require_once 'MC/Parser.php';

class ParserTest extends PHPUnit_Framework_TestCase {
    public function testLiteral() {
        $p = new MC_Parser();
        $select = $p->keyword('select', true);
        
        $select->parse('sELECT');
        $this->assertEquals(array('select'), $select->parse('select    ')->getValues());
        
        $from = $p->keyword('from', true);
        $selectfrom = $p->set($select, $from);
        $this->assertEquals(array('select', 'from'), $selectfrom->parse('select  from   ')->getValues());
    }
    
    public function testOneOf() {
        $p = new MC_Parser();
        $select = $p->keyword('select');
        $from = $p->keyword('from');
        $oneof = $p->oneOf($select, $from);
        $this->assertEquals(array('select'), $oneof->parse('select')->getValues());
        $this->assertEquals(array('from'), $oneof->parse('from')->getValues());
    }
    
    public function testOptional() {
        $p = new MC_Parser();
        $select = $p->keyword('select');
        $from = $p->keyword('from');
        $query = $p->set($p->optional($select), $p->optional($from));
        $this->assertEquals(array(), $query->parse('')->getValues());
        $this->assertEquals(array('select'), $query->parse('select')->getValues());
        $this->assertEquals(array('from'), $query->parse('from')->getValues());
        $this->assertEquals(array('select', 'from'), $query->parse('select from')->getValues());
    }
    
    public function testWord() {
        $p = new MC_Parser();
        $select = $p->keyword('select');
        $word = $p->word($p->alphas(), $p->alphanums());
        $query = $p->set($select, $word);
        $this->assertEquals(array('select', 'a90'), $query->parse('select a90  ')->getValues());
    }
    
    public function testDelimitedList() {
        $p = new MC_Parser();
        $select = $p->keyword('select');
        $word = $p->word($p->alphas());
        $query = $p->set($select, $p->delimitedList($word));
        $this->assertEquals(array('select', 'one', 'two'), $query->parse('select one,two')->getValues());
    }
    
    public function testRecursive() {
        $p = new MC_Parser();
        $expr = $p->recursive();
        $expr->replace($p->set($p->literal('{'), $p->optional($expr), $p->literal('}')));
        
        $this->assertEquals(array('{', '{', '}', '}'), $expr->parse('{{ }}')->getValues());
    }
    
    public function testDelimitedSet() {
        $p = new MC_Parser();
        $limit = $p->set($p->keyword('limit'), $p->delimitedList($p->set($p->word($p->alphanums()), $p->quotedString())));
        $this->assertEquals(array('limit', 'field', '"label"', 'field2', '"label2"'), $limit->parse('limit field "label", field2 "label2"')->getValues());
    }
    
    public function testVisParser() {
        $p = new MC_Parser();
        $ident = $p->oneOf(
            $p->word($p->alphas() . '_', $p->alphanums() . '_'),
            $p->quotedString('`')
        );
        
        $literal = $p->oneOf(
            $p->number()->name('number'),
            $p->quotedString()->name('string'),
            $p->boolean('lower')->name('boolean'),
            $p->set($p->keyword('date', true), $p->quotedString())->name('date'),
            $p->set($p->keyword('timeofday', true), $p->quotedString())->name('time'),
            $p->set(
                $p->oneOf($p->keyword('datetime', true),
                          $p->keyword('timestamp', true)),
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
        $options = $p->set($p->keyword('options', true), $p->delimitedList($p->word($p->alphas() . '_')));
        
        $query = $p->set($p->optional($select), $p->optional($from), $p->optional($where), $p->optional($groupby), $p->optional($pivot), $p->optional($limit), $p->optional($offset), $p->optional($label), $p->optional($format), $p->optional($options));
        
        $result = $query->parse('');
        $this->assertEquals(array(), $result->getValues(), 'empty query');
        
        $result = $query->parse('select *');
        $this->assertEquals(array('select', '*'), $result->getValues(), 'simple select *');
        
        $result = $query->parse('from something');
        $this->assertEquals(array('from', 'something'), $result->getValues(), 'select-less from');
        
        $result = $query->parse('select one, two from table');
        $this->assertEquals(array('select', 'one', 'two', 'from', 'table'), $result->getValues(), 'select list');
        
        $result = $query->parse('select max(one) from table where (two>1.5 and (three < 4)) group by one');
        $this->assertEquals(array('select', 'max', 'one', 'from', 'table', 'where', '(', 'two', '>', '1.5', 'and', '(', 'three', '<', '4', ')', ')', 'group', 'by', 'one'), $result->getValues());
    }
}

?>
