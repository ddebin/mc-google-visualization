<?php

namespace MC\Google;

use Exception;
use MC\Parser;
use MC\Parser\Def;
use MC\Parser\DefError;
use MC\Parser\ParseError;
use MC\Parser\Token;
use PDO;
use PDOException;

/**
 * Provide a working implementation of the Google Visualization Query data source that works with a database (or any other custom backend)
 * The documentation for the query language itself and how to use it with Google Visualizations can be found here: http://code.google.com/apis/visualization/documentation/querylanguage.html.
 */
class Visualization
{
    /**
     * The default entity that will be used if the "from" part of a query is left out.  Setting this to null
     * will make a "from" clause required.
     *
     * @var string
     */
    protected $default_entity;

    /**
     * The entity schema that defines which tables are exposed to visualization clients, along with their fields, joins, and callbacks.
     *
     * @var array
     */
    protected $entities = [];

    /**
     * If pivots are being used or MC_Google_Visualization is handling the whole request, this must be a PDO
     * connection to your database.
     *
     * @var PDO
     */
    protected $db;

    /**
     * The SQL dialect to use when auto-generating SQL statements from the parsed query tokens
     * defaults to "mysql".  Allowed values are "mysql", "postgres", or "sqlite".  Patches are welcome for the rest.
     *
     * @var string
     */
    protected $sql_dialect = 'mysql';

    /**
     * If a format string is not provided by the query, these will be used to format values by default.
     *
     * @var array
     */
    protected $default_format = [
        'date' => 'm/d/Y',
        'datetime' => 'm/d/Y h:ia',
        'time' => 'h:ia',
        'boolean' => 'FALSE:TRUE',
        'number' => 'num:0',
    ];

    /**
     * The current supported version of the Data Source protocol.
     *
     * @var float
     */
    protected $version = 0.5;

    /**
     * Create a new instance.  This must be done before the library can be used.  Pass in a PDO connection and
     * dialect if MC_Google_Visualization will handle the entire request cycle.
     *
     * @param PDO    $db      the database connection to use
     * @param string $dialect the SQL dialect to use - one of "mysql", "postgres", or "sqlite"
     *
     * @throws Visualization_Error
     */
    public function __construct(PDO $db = null, $dialect = 'mysql')
    {
        if (!function_exists('json_encode')) {
            throw new Visualization_Error('You must include the PHP json extension installed to use the MC Google Visualization Server');
        }

        $this->setDB($db);
        $this->setSqlDialect($dialect);
    }

    /**
     * Set the database connection to use when handling the entire request or getting pivot values.
     *
     * @param null|PDO $db the database connection to use - or null if you want to handle your own queries
     */
    public function setDB(PDO $db = null)
    {
        if (null !== $db) {
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        $this->db = $db;
    }

    /**
     * Set the dialect to use when generating SQL statements.
     *
     * @param string $dialect one of "mysql", "postgres", or "sqlite"
     *
     * @throws Visualization_Error
     */
    public function setSqlDialect($dialect)
    {
        if ('mysql' !== $dialect && 'postgres' !== $dialect && 'sqlite' !== $dialect) {
            throw new Visualization_Error('SQL dialects must be one of "mysql", "postgres", or "sqlite" - not "'.$dialect.'"');
        }

        $this->sql_dialect = $dialect;
    }

    /**
     * Change the default format string to use for a particular data type.
     *
     * @param string $type   the data type to change - one of "date", "datetime", "time", "boolean", or "number"
     * @param string $format the format string to use for the data type
     *
     * @throws Visualization_Error
     */
    public function setDefaultFormat($type, $format)
    {
        if (!isset($this->default_format[$type])) {
            throw new Visualization_Error('Unknown or unformattable type: "'.$type.'"');
        }
        if ('boolean' === $type && false === strpos($format, ':')) {
            throw new Visualization_Error('Invalid boolean format string: "'.$format.'"');
        }
        $this->default_format[$type] = $format;
    }

    /**
     * Handle the entire request, pulling the query from the $_GET variables and printing the results directly
     * if not specified otherwise.
     *
     * @param bool  $echo         print response and set header
     * @param array $query_params query parameters
     *
     * @throws Visualization_Error
     *
     * @return string the javascript response
     */
    public function handleRequest($echo = true, array $query_params = null)
    {
        if (null === $query_params) {
            $query_params = $_GET;
        }

        $query = $query_params['tq'];
        $params = ['version' => $this->version, 'responseHandler' => 'google.visualization.Query.setResponse'];
        $paramlist = explode(';', $query_params['tqx']);
        foreach ($paramlist as $paramstr) {
            list($name, $val) = explode(':', $paramstr);
            $params[$name] = $val;
        }

        $params['reqId'] = (int) $params['reqId'];
        $params['version'] = (float) $params['version'];
        if ($params['version'] > $this->version) {
            throw new Visualization_Error('Data Source version '.$params['version'].' is unsupported at this time');
        }

        if (isset($query_params['responseHandler'])) {
            $params['responseHandler'] = $query_params['responseHandler'];
        }

        $response = $this->handleQuery($query, $params);

        if ($echo) {
            header('Content-Type: text/javascript; charset=utf-8');
            echo $response;
        }

        return $response;
    }

    /**
     * Handle a specific query.  Use this if you want to gather the query parameters yourself instead of using handleRequest().
     *
     * @param string $query  the visualization query to parse and execute
     * @param array  $params all extra params sent along with the query - must include at least "reqId" key
     *
     * @return string the javascript response
     */
    public function handleQuery($query, array $params)
    {
        $reqid = null;
        $response = '';

        try {
            if (!($this->db instanceof PDO)) {
                throw new Visualization_Error('You must pass a PDO connection to the MC Google Visualization Server if you want to let the server handle the entire request');
            }

            $reqid = $params['reqId'];
            $query = $this->parseQuery($query);
            $meta = $this->generateMetadata($query);
            $sql = $this->generateSQL($meta);
            $meta['req_id'] = $reqid;
            $meta['req_params'] = $params;

            $stmt = $this->db->query($sql);
            //If we got here, there's no errors
            $response .= $this->getSuccessInit($meta);
            $first = true;
            foreach ($stmt as $row) {
                if (!$first) {
                    $response .= ',';
                }
                $response .= $this->getRowValues($row, $meta);
                $first = false;
            }

            $response .= $this->getSuccessClose();
        } catch (Visualization_Error $e) {
            $response .= $this->handleError($reqid, $e->getMessage(), $params['responseHandler'], $e->type, $e->summary);
        } catch (PDOException $e) {
            $response .= $this->handleError($reqid, $e->getMessage(), $params['responseHandler'], 'invalid_query', 'Invalid Query - PDO exception');
        } catch (ParseError $e) {
            $response .= $this->handleError($reqid, $e->getMessage(), $params['responseHandler'], 'invalid_query', 'Invalid Query - Parse Error');
        } catch (Exception $e) {
            $response .= $this->handleError($reqid, $e->getMessage(), $params['responseHandler']);
        }

        return $response;
    }

    /**
     * Given the results of parseQuery(), introspect against the entity definitions provided and return the metadata array used to generate the SQL.
     *
     * @param array $query the visualization query broken up into sections
     *
     * @throws Visualization_QueryError
     * @throws Visualization_Error
     *
     * @return array the metadata array from merging the query with the entity table definitions
     */
    public function generateMetadata(array $query)
    {
        $meta = [];
        if (!isset($query['from']) && null === $this->default_entity) {
            throw new Visualization_Error('FROM clauses are required if no default entity is defined');
        }
        if (!isset($query['from'])) {
            $query['from'] = $this->default_entity;
        }

        if (!isset($this->entities[$query['from']])) {
            throw new Visualization_QueryError('Unknown table "'.$query['from'].'"');
        }

        $meta['entity_name'] = $query['from'];
        $entity = $this->entities[$query['from']];
        $meta['entity'] = $entity;
        $meta['table'] = $entity['table'];
        if (isset($entity['where'])) {
            $meta['global_where'] = $entity['where'];
        }

        if (!isset($query['select'])) {
            //By default, return all fields defined for an entity
            $query['select'] = array_keys($entity['fields']);
        }

        //The query fields might be different from the "select" fields (callback dependant fields will not be returned)
        $meta['query_fields'] = [];
        $meta['joins'] = [];
        $meta['field_spec'] = [];

        foreach ($query['select'] as $sfield) {
            if (is_array($sfield)) {
                $field = $sfield[1];
            } else {
                $field = $sfield;
            }

            if (!isset($entity['fields'][$field])) {
                throw new Visualization_QueryError('Unknown column "'.$field.'"');
            }

            $field_spec = $entity['fields'][$field];
            if (isset($field_spec['join']) && !isset($meta['joins'][$field_spec['join']])) {
                $meta['joins'][$field_spec['join']] = $entity['joins'][$field_spec['join']];
            }

            if (isset($field_spec['callback'])) {
                if (isset($meta['pivot'])) {
                    throw new Visualization_QueryError('Callback-based fields cannot be used in pivot queries');
                }

                if (is_array($sfield)) {
                    throw new Visualization_Error('Callback-based fields cannot have functions called on them');
                }

                $this->addDependantCallbackFields($field_spec, $entity, $meta);
            } elseif (!in_array($sfield, $meta['query_fields'], true)) {
                $meta['query_fields'][] = $sfield;
            }

            $meta['field_spec'][$field] = $field_spec;
        }

        $meta['select'] = $query['select'];

        if (isset($query['where'])) {
            //Parse the where clauses and error out on non-existant and callback fields and add joins
            foreach ($query['where'] as $where_token) {
                if ('where_field' === $where_token['type']) {
                    $field = $where_token['value'];
                    if (!isset($entity['fields'][$field])) {
                        throw new Visualization_QueryError('Unknown column in WHERE clause "'.$field.'"');
                    }
                    if (isset($entity['fields'][$field]['callback'])) {
                        throw new Visualization_QueryError('Callback fields cannot be included in WHERE clauses');
                    }

                    $field_spec = $entity['fields'][$field];
                    if (isset($field_spec['join']) && !isset($meta['joins'][$field_spec['join']])) {
                        $meta['joins'][$field_spec['join']] = $entity['joins'][$field_spec['join']];
                    }

                    $meta['field_spec'][$field] = $field_spec;
                }
            }
        }

        //Also add the joins & field spec information for the orderby, groupby, and pivot clauses
        if (isset($query['pivot'])) {
            foreach ($query['pivot'] as $field) {
                if (!isset($entity['fields'][$field])) {
                    throw new Visualization_QueryError('Unknown column in PIVOT clause "'.$field.'"');
                }

                $field_spec = $entity['fields'][$field];
                if (isset($field_spec['join']) && !isset($meta['joins'][$field_spec['join']])) {
                    $meta['joins'][$field_spec['join']] = $entity['joins'][$field_spec['join']];
                }
                $meta['field_spec'][$field] = $field_spec;
            }
        }

        if (isset($query['groupby'])) {
            foreach ($query['groupby'] as $field) {
                if (!isset($entity['fields'][$field])) {
                    throw new Visualization_QueryError('Unknown column in GROUP BY clause "'.$field.'"');
                }

                $field_spec = $entity['fields'][$field];

                if (isset($field_spec['callback'])) {
                    throw new Visualization_QueryError('Callback-based fields cannot be used in GROUP BY clauses');
                }

                if (isset($field_spec['join']) && !isset($meta['joins'][$field_spec['join']])) {
                    $meta['joins'][$field_spec['join']] = $entity['joins'][$field_spec['join']];
                }
                $meta['field_spec'][$field] = $field_spec;
            }
        }

        if (isset($query['orderby'])) {
            foreach (array_keys($query['orderby']) as $field) {
                if (!isset($entity['fields'][$field])) {
                    throw new Visualization_QueryError('Unknown column in ORDER BY clause "'.$field.'"');
                }

                $field_spec = $entity['fields'][$field];
                $meta['field_spec'][$field] = $field_spec;

                if (isset($field_spec['sort_field'])) {
                    $field = $field_spec['sort_field'];
                    $field_spec = $entity['fields'][$field_spec['sort_field']];
                }

                if (isset($field_spec['callback'])) {
                    throw new Visualization_QueryError('Callback-based fields cannot be used in ORDER BY clauses');
                }

                if (isset($field_spec['join']) && !isset($meta['joins'][$field_spec['join']])) {
                    $meta['joins'][$field_spec['join']] = $entity['joins'][$field_spec['join']];
                }
                $meta['field_spec'][$field] = $field_spec;
            }
        }

        //Some of the query information we just copy into the metadata array
        $copy_keys = ['where', 'orderby', 'groupby', 'pivot', 'limit', 'offset', 'labels', 'formats', 'options'];
        foreach ($copy_keys as $copy_key) {
            if (isset($query[$copy_key])) {
                $meta[$copy_key] = $query[$copy_key];
            }
        }

        return $meta;
    }

    /**
     * Add a new entity (table) to the visualization server that maps onto one or more SQL database tables.
     *
     * @param string $name the name of the entity - should be used in the "from" clause of visualization queries
     * @param array  $spec optional spec array with keys "fields", "joins", "table", and "where" to define the mapping between visualization queries and SQL queries
     *
     * @throws Visualization_Error
     */
    public function addEntity($name, array $spec = [])
    {
        $entity = ['table' => isset($spec['table']) ? $spec['table'] : $name, 'fields' => [], 'joins' => []];
        $this->entities[$name] = $entity;

        if (isset($spec['fields'])) {
            foreach ($spec['fields'] as $field_name => $field_spec) {
                $this->addEntityField($name, $field_name, $field_spec);
            }
        }

        if (isset($spec['joins'])) {
            foreach ($spec['joins'] as $join_name => $join_sql) {
                $this->addEntityJoin($name, $join_name, $join_sql);
            }
        }

        if (isset($spec['where'])) {
            $this->setEntityWhere($name, $spec['where']);
        }
    }

    /**
     * Set the default entity to be used when a "from" clause is omitted from a query.  Set to null to require a "from" clause for all queries.
     *
     * @param null|string $default the new default entity
     *
     * @throws Visualization_Error
     */
    public function setDefaultEntity($default = null)
    {
        if (null !== $default && !isset($this->entities[$default])) {
            throw new Visualization_Error('No entity exists with name "'.$default.'"');
        }

        $this->default_entity = $default;
    }

    /**
     * Given an associative array of key => value pairs and the results of generateMetadata, return the visualization results fragment for the particular row.
     *
     * @param array $row  the row values as an array
     * @param array $meta the metadata for the query (use generateMetadata())
     *
     * @throws Visualization_Error
     *
     * @return string the string fragment to include in the results back to the javascript client
     */
    public function getRowValues(array $row, array $meta)
    {
        $vals = [];
        foreach ($meta['select'] as $field) {
            if (is_array($field)) {
                $function = $field[0];
                if (isset($field[2])) {
                    $key = implode(',', $field[2]).' '.$function.'-'.$field[1];
                } else {
                    $key = $function.'-'.$field[1];
                }
                $field = $field[1];
            } else {
                $function = null;
                $key = $field;
            }

            $callback_response = null;

            $field_meta = $meta['field_spec'][$field];
            if (isset($field_meta['callback'])) {
                if (isset($field_meta['extra'])) {
                    $params = [$row, $field_meta['fields']];
                    $params = array_merge($params, $field_meta['extra']);
                    $callback_response = call_user_func_array($field_meta['callback'], $params);
                } else {
                    $callback_response = call_user_func($field_meta['callback'], $row, $field_meta['fields']);
                }
                if (is_array($callback_response)) {
                    $val = $callback_response['value'];
                } else {
                    $val = $callback_response;
                }
            } else {
                $val = $row[$key];
            }

            $type = isset($function) ? 'number' : $field_meta['type'];

            $format = '';
            if (isset($meta['formats'][$field])) {
                $format = $meta['formats'][$field];
            } elseif (isset($this->default_format[$type])) {
                $format = $this->default_format[$type];
            }

            switch ($type) {
                case '':
                case null:
                case 'text':
                    $val = json_encode((string) $val);
                    $formatted = null;

                    break;
                case 'number':
                    $val = (float) $val;
                    if (preg_match('/^num:(\d+)(.*)$/i', $format, $matches)) {
                        $digits = (int) $matches[1];
                        $extras = $matches[2];
                        if (is_array($extras) && (2 === count($extras))) {
                            $formatted = number_format($val, $digits, $extras[0], $extras[1]);
                        } else {
                            $formatted = number_format($val, $digits);
                        }
                    } elseif ('dollars' === $format) {
                        $formatted = '$'.number_format($val, 2);
                    } elseif ('percent' === $format) {
                        $formatted = number_format($val * 100, 1).'%';
                    } else {
                        $formatted = sprintf($format, $val);
                    }
                    $val = json_encode($val);

                    break;
                case 'boolean':
                    $val = (bool) $val;
                    list($format_false, $format_true) = explode(':', $format, 2);
                    $formatted = $val ? $format_true : $format_false;
                    $val = json_encode((bool) $val);

                    break;
                case 'date':
                    if (!is_numeric($val) || 6 !== strlen($val)) {
                        $time = strtotime($val);
                        list($year, $month, $day) = explode('-', date('Y-m-d', $time));
                        $formatted = date($format, $time);
                    } else {
                        $year = substr($val, 0, 4);
                        $week = substr($val, -2);
                        $time = strtotime($year.'0104 +'.$week.' weeks');
                        $monday = strtotime('-'.((int) date('w', $time) - 1).' days', $time);
                        list($year, $month, $day) = explode('-', date('Y-m-d', $monday));
                        $formatted = date($format, $monday);
                    }
                    $val = 'new Date('.(int) $year.','.((int) $month - 1).','.(int) $day.')';

                    break;
                case 'datetime':
                case 'timestamp':
                    $time = strtotime($val);
                    list($year, $month, $day, $hour, $minute, $second) = explode('-', date('Y-m-d-H-i-s', $time));
                    // MALC - Force us to consider the date as UTC...
                    $val = 'new Date(Date.UTC('.(int) $year.','.((int) $month - 1).','.(int) $day.','.(int) $hour.','.(int) $minute.','.(int) $second.'))';
                    $formatted = date($format, $time);

                    break;
                case 'time':
                    $time = strtotime($val);
                    list($hour, $minute, $second) = explode('-', date('H-i-s', $time));
                    $val = '['.(int) $hour.','.(int) $minute.','.(int) $second.',0]';
                    $formatted = date($format, $time);

                    break;
                case 'binary':
                    $formatted = '0x'.current(unpack('H*', $val));
                    $val = '0x'.current(unpack('H*', $val));
                    $val = json_encode($val);

                    break;
                default:
                    throw new Visualization_Error('Unknown field type "'.$type.'"');
            }

            if (isset($callback_response['formatted'])) {
                $formatted = $callback_response['formatted'];
            }

            if (!isset($meta['options']['no_values'])) {
                $cell = '{v:'.$val;
                if (!isset($meta['options']['no_format'])) {
                    if (null !== $formatted) {
                        $cell .= ',f:'.json_encode($formatted);
                    }
                }
            } else {
                $cell = '{f:'.json_encode($formatted);
            }

            $vals[] = $cell.'}';
        }

        return '{c:['.implode(',', $vals).']}';
    }

    /**
     * A utility method for testing - take a visualization query, and return the SQL that would be generated.
     *
     * @param string $query the visualization query to run
     *
     * @throws Visualization_QueryError
     * @throws Visualization_Error
     * @throws ParseError
     * @throws DefError
     *
     * @return string the SQL that should be sent to the database
     */
    public function getSQL($query)
    {
        $tokens = $this->parseQuery($query);
        $meta = $this->generateMetadata($tokens);

        return $this->generateSQL($meta);
    }

    /**
     * Use MC_Parser to generate a grammar that matches the query language specified here: http://code.google.com/apis/visualization/documentation/querylanguage.html.
     *
     * @throws DefError
     *
     * @return Def the grammar for the query language
     */
    public function getGrammar()
    {
        $p = new Parser();
        $ident = $p->oneOf(
            $p->word($p->alphas().'_', $p->alphanums().'_'),
            $p->quotedString('`')
        );

        $literal = $p->oneOf(
            $p->number()->name('number'),
            $p->hexNumber()->name('number'),
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

        // Malc - Added 'Like' 20130219
        $comparison = $p->oneOf($p->literal('like'), $p->literal('<'), $p->literal('<='), $p->literal('>'), $p->literal('>='), $p->literal('='), $p->literal('!='), $p->literal('<>'))->name('operator');

        $expr = $p->recursive();
        $value = $p->oneOf($literal, $ident->name('where_field'));
        $cond = $p->oneOf(
            $p->set($value, $comparison, $value),
            $p->set($value, $p->set($p->keyword('is', true), $p->literal('null', true))->name('isnull')),
            $p->set($value, $p->set($p->keyword('is', true), $p->keyword('not', true), $p->literal('null', true))->name('notnull')),
            $p->set($p->literal('(')->name('sep'), $expr, $p->literal(')')->name('sep'))
        );

        $andor = $p->oneOf($p->keyword('and', true), $p->keyword('or', true))->name('andor_sep');

        $expr->replace($p->set($cond, $p->zeroOrMore($p->set($andor, $expr))));

        $where = $p->set($p->keyword('where', true), $expr)->name('where');

        $groupby = $p->set($p->keyword('group', true), $p->keyword('by', true), $p->delimitedList($ident))->name('groupby');
        $pivot = $p->set($p->keyword('pivot', true), $p->delimitedList($ident))->name('pivot');

        $orderby_clause = $p->set($ident, $p->optional($p->oneOf($p->literal('asc', true), $p->literal('desc', true))));
        $orderby = $p->set($p->keyword('order', true), $p->keyword('by', true), $p->delimitedList($orderby_clause))->name('orderby');
        $limit = $p->set($p->keyword('limit', true), $p->word($p->nums()))->name('limit');
        $offset = $p->set($p->keyword('offset', true), $p->word($p->nums()))->name('offset');
        $label = $p->set($p->keyword('label', true), $p->delimitedList($p->set($ident, $p->quotedString())))->name('label');
        $format = $p->set($p->keyword('format', true), $p->delimitedList($p->set($ident, $p->quotedString())))->name('format');
        $options = $p->set($p->keyword('options', true), $p->delimitedList($p->word($p->alphas().'_')))->name('options');

        return $p->set($p->optional($select), $p->optional($from), $p->optional($where), $p->optional($groupby), $p->optional($pivot), $p->optional($orderby), $p->optional($limit), $p->optional($offset), $p->optional($label), $p->optional($format), $p->optional($options));
    }

    /**
     * Parse the query according to the visualization query grammar, and break down the constituent parts.
     *
     * @param string $str the query string to parse
     *
     * @throws ParseError
     * @throws Visualization_QueryError
     * @throws Parser\DefError
     *
     * @return array the parsed query as an array, keyed by each part of the query (select, from, where, groupby, pivot, orderby, limit, offset, label, format, options
     */
    public function parseQuery($str)
    {
        $query = [];
        $tokens = $this->getGrammar()->parse($str);

        foreach ($tokens->getChildren() as $token) {
            switch ($token->name) {
                case 'select':
                    $sfields = $token->getChildren();
                    $sfields = $sfields[1];

                    $this->parseFieldTokens($sfields, $fields);
                    $query['select'] = $fields;

                    break;
                case 'from':
                    $vals = $token->getValues();
                    $query['from'] = $vals[1];

                    break;
                case 'where':
                    $where_tokens = $token->getChildren();
                    $where_tokens = $where_tokens[1];
                    $this->parseWhereTokens($where_tokens, $where);
                    $query['where'] = $where;

                    break;
                case 'groupby':
                    $groupby = $token->getValues();
                    array_shift($groupby);
                    array_shift($groupby);
                    $query['groupby'] = $groupby;

                    break;
                case 'pivot':
                    if (null === $this->db) {
                        throw new Visualization_QueryError('Pivots require a PDO database connection');
                    }
                    $pivot = $token->getValues();
                    array_shift($pivot);
                    $query['pivot'] = $pivot;

                    break;
                case 'orderby':
                    $orderby = $token->getValues();
                    array_shift($orderby);
                    array_shift($orderby);
                    $field_dir = [];
                    $order_cnt = count($orderby);
                    for ($i = 0; $i < $order_cnt; ++$i) {
                        $field = $orderby[$i];
                        $dir = 'asc';
                        if (isset($orderby[$i + 1])) {
                            $dir = strtolower($orderby[$i + 1]);
                            if ('asc' === $dir || 'desc' === $dir) {
                                ++$i;
                            } else {
                                $dir = 'asc';
                            }
                        }
                        $field_dir[$field] = $dir;
                    }
                    $query['orderby'] = $field_dir;

                    break;
                case 'limit':
                    $limit = $token->getValues();
                    $limit = $limit[1];
                    $query['limit'] = $limit;

                    break;
                case 'offset':
                    $offset = $token->getValues();
                    $offset = $offset[1];
                    $query['offset'] = $offset;

                    break;
                case 'label':
                    $labels = $token->getValues();
                    array_shift($labels);

                    $query_labels = [];
                    for ($i = 0; $i < count($labels); $i += 2) {
                        $field = $labels[$i];
                        $label = trim($labels[$i + 1], '\'"');
                        $query_labels[$field] = $label;
                    }
                    $query['labels'] = $query_labels;

                    break;
                case 'format':
                    $formats = $token->getValues();
                    array_shift($formats);

                    $query_formats = [];
                    for ($i = 0; $i < count($formats); $i += 2) {
                        $field = $formats[$i];
                        $query_formats[$field] = trim($formats[$i + 1], '\'"');
                    }
                    $query['formats'] = $query_formats;

                    break;
                case 'options':
                    $qoptions = $token->getValues();
                    array_shift($qoptions);
                    $options = [];
                    foreach ($qoptions as $option) {
                        $options[$option] = true;
                    }
                    $query['options'] = $options;

                    break;
                default:
                    throw new Visualization_QueryError('Unknown query clause "'.$token->name.'"');
            }
        }

        return $query;
    }

    /**
     * Return the response appropriate to tell the visualization client that an error has occurred.
     *
     * @param int    $reqid       the request ID that caused the error
     * @param string $detail_msg  the detailed message to send along with the error
     * @param string $handler
     * @param string $code        the code for the error (like "error", "server_error", "invalid_query", "access_denied", etc.)
     * @param string $summary_msg a short description of the error, appropriate to show to end users
     *
     * @return string the string to output that will cause the visualization client to detect an error
     */
    protected function handleError($reqid, $detail_msg, $handler = 'google.visualization.Query.setResponse', $code = 'error', $summary_msg = null)
    {
        if (null === $summary_msg) {
            $summary_msg = $detail_msg;
        }
        $handler = $handler ?: 'google.visualization.Query.setResponse';

        return $handler.'({version:"'.$this->version.'",reqId:"'.$reqid.'",status:"error",errors:[{reason:'.json_encode($code).',message:'.json_encode($summary_msg).',detailed_message:'.json_encode($detail_msg).'}]});';
    }

    /**
     * Add a new field to an entity table.
     *
     * @param string $entity the name of the entity to add the field to
     * @param string $field  the name of the field
     * @param array  $spec   the metadata for the field as a set of key-value pairs - allowed keys are "field", "callback", "fields", "extra", "sort_field", "type", and "join"
     *
     * @throws Visualization_Error
     */
    protected function addEntityField($entity, $field, array $spec)
    {
        if (!isset($spec['field']) && !isset($spec['callback'])) {
            throw new Visualization_Error('Entity fields must either be mapped to database fields or given callback functions');
        }

        if (!isset($this->entities[$entity])) {
            throw new Visualization_Error('No entity table defined with name "'.$entity.'"');
        }

        if (!isset($spec['callback']) && (isset($spec['fields']) || isset($spec['extra']))) {
            throw new Visualization_Error('"fields" and "extra" parameters only apply to callback fields');
        }

        $this->entities[$entity]['fields'][$field] = $spec;
    }

    /**
     * Add a new optional join to the entity table.  If fields associated with this join are selected, the join will be added to the SQL query.
     *
     * @param string $entity the name of the entity table to add the join to
     * @param string $join   the name of the join.  Set the entity field's "join" key to this
     * @param string $sql    the SQL for the join that will be injected into the query
     *
     * @throws Visualization_Error
     */
    protected function addEntityJoin($entity, $join, $sql)
    {
        if (!isset($this->entities[$entity])) {
            throw new Visualization_Error('No entity table defined with name "'.$entity.'"');
        }

        $this->entities[$entity]['joins'][$join] = $sql;
    }

    /**
     * Add a particular "WHERE" clause to all queries against an entity table.
     *
     * @param string $entity the name of the entity to add the filter to
     * @param string $where  the SQL WHERE condition to add to all queries against $entity
     *
     * @throws Visualization_Error
     */
    protected function setEntityWhere($entity, $where)
    {
        if (!isset($this->entities[$entity])) {
            throw new Visualization_Error('No entity table defined with name "'.$entity.'"');
        }

        $this->entities[$entity]['where'] = $where;
    }

    /**
     * Given the metadata for a query and the entities it's working against, generate the SQL.
     *
     * @param array $meta the results of generateMetadata() on the parsed visualization query
     *
     * @throws Visualization_QueryError
     *
     * @return string the SQL version of the visualization query
     */
    protected function generateSQL(array &$meta)
    {
        if (!isset($meta['query_fields'])) {
            $meta['query_fields'] = $meta['select'];
        }

        if (isset($meta['pivot'])) {
            //Pivot queries are special - they require an entity to be passed and modify the query directly
            $entity = $meta['entity'];
            $pivot_fields = [];
            $pivot_joins = [];
            $pivot_group = [];
            foreach ($meta['pivot'] as $entity_field) {
                $field = $entity['fields'][$entity_field];
                if (isset($field['callback'])) {
                    throw new Visualization_QueryError('Callback fields cannot be used as pivots: "'.$entity_field.'"');
                }
                $pivot_fields[] = $field['field'].' AS '.$entity_field;
                $pivot_group[] = $entity_field;
                if (isset($field['join']) && !in_array($entity['joins'][$field['join']], $pivot_joins, true)) {
                    $pivot_joins[] = $entity['joins'][$field['join']];
                }
            }

            $pivot_sql = 'SELECT '.implode(', ', $pivot_fields).' FROM '.$meta['table'];
            if (!empty($pivot_joins)) {
                $pivot_sql .= ' '.implode(' ', $pivot_joins);
            }
            $pivot_sql .= ' GROUP BY '.implode(', ', $pivot_group);

            $func_fields = [];
            $new_fields = [];
            foreach ($meta['query_fields'] as $field) {
                if (is_array($field)) {
                    $func_fields[] = $field;
                } else {
                    $new_fields[] = $field;
                }
            }
            $meta['query_fields'] = $new_fields;

            $stmt = $this->db->query($pivot_sql);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                //Create a version of all function-ed fields for each unique combination of pivot values
                foreach ($func_fields as $field) {
                    $field[2] = $row;

                    $meta['query_fields'][] = $field;
                }
            }

            //For pivot queries, the fields we return and the fields we query against are always the same
            $meta['select'] = $meta['query_fields'];
        }

        $query_sql = [];
        $join_sql = $meta['joins'];
        foreach ($meta['query_fields'] as $field) {
            $func = null;
            $pivot_cond = null;
            if (is_array($field)) {
                $func = $field[0];
                $pivot_cond = isset($field[2]) ? $field[2] : null;
                $field = $field[1];
            }
            $query_sql[] = $this->getFieldSQL($field, $meta['field_spec'][$field], true, $func, $pivot_cond, $meta['field_spec']);
        }

        $where_str = null;
        if (isset($meta['where'])) {
            $where_str = [];
            foreach ($meta['where'] as &$where_part) {
                //Replace field references with their SQL forms
                switch ($where_part['type']) {
                    case 'where_field':
                        $where_part['value'] = $this->getFieldSQL($where_part['value'], $meta['field_spec'][$where_part['value']]);

                        break;
                    case 'datetime':
                    case 'timestamp':
                        $where_part['value'] = $this->convertDateTime(trim($where_part['value'][1], '\'"'));

                        break;
                    case 'timeofday':
                        $where_part['value'] = $this->convertTime(trim($where_part['value'][1], '\'"'));

                        break;
                    case 'date':
                        $where_part['value'] = $this->convertDate(trim($where_part['value'][1], '\'"'));

                        break;
                    case 'null':
                    case 'notnull':
                        $where_part['value'] = strtoupper(implode(' ', $where_part['value']));

                        break;
                }

                $where_str[] = $where_part['value'];
            }
            $where_str = implode(' ', $where_str);
        }

        $sql = 'SELECT '.implode(', ', $query_sql).' FROM '.$meta['table'];
        if (!empty($join_sql)) {
            $sql .= ' '.implode(' ', $join_sql);
        }

        if ($where_str || isset($meta['global_where'])) {
            if (!$where_str) {
                $where_str = '1=1';
            }
            $sql .= ' WHERE ('.$where_str.')';
            if (isset($meta['global_where'])) {
                $sql .= ' AND '.$meta['global_where'];
            }
        }

        if (isset($meta['groupby'])) {
            $group_sql = [];
            foreach ($meta['groupby'] as $group) {
                $group_sql[] = $this->getFieldSQL($group, $meta['field_spec'][$group]);
            }
            $sql .= ' GROUP BY '.implode(', ', $group_sql);
        }

        if (isset($meta['orderby'])) {
            $sql .= ' ORDER BY';
            $first = true;
            foreach ($meta['orderby'] as $field => $dir) {
                if (isset($meta['field_spec'][$field]['sort_field'])) {
                    //An entity field can delegate sorting to another field by using the "sort_field" key
                    $field = $meta['field_spec'][$field]['sort_field'];
                }
                $spec = $meta['field_spec'][$field];
                if (!$first) {
                    $sql .= ',';
                }

                $sql .= ' '.$this->getFieldSQL($field, $spec).' '.strtoupper($dir);
                $first = false;
            }
        }

        if (isset($meta['limit']) || isset($meta['offset'])) {
            $sql .= $this->convertLimit($meta['limit'], $meta['offset']);
        }

        return $sql;
    }

    /**
     * Return the beginning of a visualization response from the query metadata (everything before the actual row data).
     *
     * @param array $meta the metadata for the query - generally generated by MC_Google_Visualization::generateMetadata
     *
     * @throws Visualization_Error
     *
     * @return string the initial output string for a successful query
     */
    protected function getSuccessInit(array $meta)
    {
        $handler = $meta['req_params']['responseHandler'] ?: 'google.visualization.Query.setResponse';
        $version = $meta['req_params']['version'] ?: $this->version;

        return $handler."({version:'".$version."',reqId:'".$meta['req_id']."',status:'ok',table:".$this->getTableInit($meta);
    }

    /**
     * Return the table metadata section of the visualization response for a successful query.
     *
     * @param array $meta the metadata for the query - generally generated by MC_Google_Visualization::generateMetadata
     *
     * @throws Visualization_Error
     *
     * @return string
     */
    protected function getTableInit(array $meta)
    {
        $field_init = [];
        foreach ($meta['select'] as $field) {
            if (is_array($field)) {
                $function = $field[0];
                if (!isset($field[2])) {
                    $field_id = $function.'-'.$field[1];
                } else {
                    $field_id = implode(',', $field[2]).' '.$function.'-'.$field[1];
                }
                $field = $field[1];
            } else {
                $function = null;
                $field_id = $field;
            }

            $label = isset($meta['labels'][$field]) ? $meta['labels'][$field] : $field_id;
            $type = isset($meta['field_spec'][$field]['type']) ? $meta['field_spec'][$field]['type'] : 'text';
            if (isset($function)) {
                $type = 'number';
            }

            switch ($type) {
                case 'text':
                case 'binary':
                    $rtype = 'string';

                    break;
                case 'number':
                    $rtype = 'number';

                    break;
                case 'boolean':
                    $rtype = 'boolean';

                    break;
                case 'date':
                    $rtype = 'date';

                    break;
                case 'datetime':
                case 'timestamp':
                    $rtype = 'datetime';

                    break;
                case 'time':
                    $rtype = 'time';

                    break;
                default:
                    throw new Visualization_Error('Unknown field type "'.$type.'"');
            }

            $field_init[] = "{id:'".$field_id."',label:".json_encode($label).",type:'".$rtype."'}";
        }

        return '{cols: ['.implode(',', $field_init).'],rows: [';
    }

    protected function getSuccessClose()
    {
        return ']}});';
    }

    /**
     * Convert a visualization date into the appropriate date-literal format for the SQL dialect.
     *
     * @param string $value the date as a string "YYY-MM-DD"
     *
     * @return string the same value converted to be used inline in a SQL query
     */
    protected function convertDate($value)
    {
        return "'".$value."'";
    }

    /**
     * Convert a visualization date/time into the appropriate literal format for the SQL dialect.
     *
     * @param string $value the date/time as a string "YYY-MM-DD HH:NN:SS"
     *
     * @return string the same value converted to be used inline in a SQL query
     */
    protected function convertDateTime($value)
    {
        return "'".$value."'";
    }

    /**
     * Convert a visualization time into the appropriate literal format for the SQL dialect.
     *
     * @param string $value the time as a string "HH:NN:SS"
     *
     * @return string the same value converted to be used inline in a SQL query
     */
    protected function convertTime($value)
    {
        return "'".$value."'";
    }

    /**
     * Convert the limit and offset clauses from the visualization query into SQL.
     *
     * @param null|int $limit  the limit value, or null if not provided
     * @param null|int $offset the offset value, or null if not provided
     *
     * @return string the limit clause converted to be used inline in a SQL query
     */
    protected function convertLimit($limit, $offset)
    {
        $sql = '';
        if (null !== $limit) {
            $sql .= ' LIMIT '.$limit;
        }
        if (null !== $offset) {
            $sql .= ' OFFSET '.$offset;
        }

        return $sql;
    }

    /**
     * Return the character used to quote aliases for this query SQL dialect
     * $return string the quote character.
     */
    protected function getFieldQuote()
    {
        switch ($this->sql_dialect) {
            case 'postgres':
                return '"';
            default:
                return '`';
        }
    }

    /**
     * Helper function to generate the SQL for a given entity field.
     *
     * @param string      $name         the name of the field to generate SQL for
     * @param array       $spec         the entity spec array for the field
     * @param bool        $alias        whether to also generate an "AS" alias for the field - defaults to false
     * @param null|string $func         the function to call against the field (count, avg, sum, max, min)
     * @param null|array  $pivot        if there was a pivot for this query, this should be an array of values that uniquely identify this field
     * @param null|array  $pivot_fields if there was a pivot for this query, this should be an array of the specs for the pivoted fields
     *
     * @return string the SQL string for this field, with an op
     */
    protected function getFieldSQL($name, $spec, $alias = false, $func = null, array $pivot = null, array $pivot_fields = null)
    {
        $sql = $spec['field'];
        $q = $this->getFieldQuote();
        if (null !== $func) {
            if (null === $pivot) {
                $sql = strtoupper($func).'('.$sql.')';
                if ($alias) {
                    $sql .= ' AS '.$q.$func.'-'.$name.$q;
                }
            } else {
                $casewhen = [];
                foreach ($pivot as $key => $val) {
                    $pivot_field = $pivot_fields[$key];
                    $casewhen[] = $pivot_field['field'].'='.$this->db->quote($val);
                }
                $sql = strtoupper($func).'(CASE WHEN '.implode(' AND ', $casewhen).' THEN '.$sql.' ELSE NULL END)';
                if ($alias) {
                    $sql .= ' AS '.$q.implode(',', $pivot).' '.$func.'-'.$name.$q;
                }
            }
        } elseif ($alias) {
            $sql .= ' AS '.$name;
        }

        return $sql;
    }

    /**
     * Recursively process the dependant fields for callback entity fields.
     *
     * @param array $field  the spec array for the field to add (must have a "callback" key)
     * @param array $entity the spec array for the entity to pull other fields from
     * @param array $meta   the query metadata array to append the results
     *
     * @throws Visualization_Error
     */
    protected function addDependantCallbackFields(array $field, array $entity, array &$meta)
    {
        foreach ($field['fields'] as $dependant) {
            if (!isset($entity['fields'][$dependant])) {
                throw new Visualization_Error('Unknown callback required field "'.$dependant.'"');
            }

            $dependant_field = $entity['fields'][$dependant];
            $meta['field_spec'][$dependant] = $dependant_field;
            if (isset($dependant_field['callback'])) {
                $this->addDependantCallbackFields($dependant_field, $entity, $meta);
            } elseif (!in_array($dependant, $meta['query_fields'], true)) {
                if (isset($dependant_field['join']) && !isset($meta['joins'][$dependant_field['join']])) {
                    $meta['joins'][$dependant_field['join']] = $entity['joins'][$dependant_field['join']];
                }
                $meta['query_fields'][] = $dependant;
            }
        }
    }

    /**
     * Helper method for the query parser to recursively scan the delimited list of select fields.
     *
     * @param Token      $token  the token or token group to recursively parse
     * @param null|array $fields the collector array reference to receive the flattened select field values
     */
    protected function parseFieldTokens($token, array &$fields = null)
    {
        if ('*' === $token->value) {
            return;
        }

        if (!is_array($fields)) {
            $fields = [];
        }

        if ($token->hasChildren()) {
            if ('function' === $token->name) {
                $field = $token->getValues();
                $field[0] = strtolower($field[0]);
                $fields[] = $field;
            } else {
                foreach ($token->getChildren() as $field) {
                    $this->parseFieldTokens($field, $fields);
                }
            }
        } else {
            $fields[] = $token->value;
        }
    }

    /**
     * Helper method for the query parser to recursively scan and flatten the where clause's conditions.
     *
     * @param Token      $token the token or token group to parse
     * @param null|array $where the collector array of tokens that make up the where clause
     */
    protected function parseWhereTokens($token, array &$where = null)
    {
        if (!is_array($where)) {
            $where = [];
        }
        if ($token->hasChildren()) {
            if ($token->name) {
                $where[] = ['type' => $token->name, 'value' => $token->getValues()];
            } else {
                foreach ($token->getChildren() as $child) {
                    $this->parseWhereTokens($child, $where);
                }
            }
        } elseif ($token->name) {
            $where[] = ['type' => $token->name, 'value' => $token->value];
        }
    }
}
