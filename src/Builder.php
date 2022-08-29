<?php

namespace webrium\foxql;

class Builder extends DB
{

  use Process;


  protected $CONFIG;
  protected $TABLE;
  protected $PARAMS = [];
  protected $ACTION = 'select';

  protected $SOURCE_VALUE = [];



  // public function __construct(){

  // }

  // public function __construct(Config $config){
  //   $this->CONFIG = $config;
  // }

  public function setTable($name)
  {
    $this->TABLE = $name;
  }

  public function execute($query, $params = [], $return = false)
  {
    if (!$this->CONFIG)
      $this->CONFIG = DB::$CONFIG_LIST[DB::$USE_DATABASE];

    $this->CONFIG->connect();
    $this->PARAMS = $params;

    if ($this->PARAMS == null) {
      $stmt = $this->CONFIG->pdo()->query($query);
    } else {
      $stmt = $this->CONFIG->pdo()->prepare($query);
      $stmt->execute($this->PARAMS);
    }

    if ($return) {
      return $stmt->fetchAll($this->CONFIG->getFetch());
    } else {
      return $stmt->rowCount();
    }
  }

  public function addOperator($oprator)
  {
    $array = $this->getSourceValueItem('WHERE');

    if (count($array) > 0) {

      $end = $array[count($array) - 1];

      if (in_array($end, ['AND', 'OR', '(']) == false) {
        $this->addToSourceArray('WHERE', $oprator);
      }
    } else {
      $this->addToSourceArray('WHERE', 'WHERE');
    }
  }

  public function addOperatorHaving($oprator)
  {
    $array = $this->getSourceValueItem('HAVING');

    if (count($array) > 0) {

      $end = $array[count($array) - 1];

      if (in_array($end, ['AND', 'OR', '(']) == false) {
        $this->addToSourceArray('HAVING', $oprator);
      }
    }
  }

  public function addStartParentheses()
  {
    $this->addToSourceArray('WHERE', '(');
  }

  public function addEndParentheses()
  {
    $this->addToSourceArray('WHERE', ')');
  }




  public function select(...$args)
  {
    if (count($args) == 1) {
      if (is_string($args[0])) {
        $this->addToSourceArray('DISTINCT', $args[0]);
      } elseif (is_array($args[0])) {
        foreach ($args[0] as $key => $arg) {
          $args[0][$key] = $this->fix_column_name($arg)['name'];
        }

        $this->addToSourceArray('DISTINCT', implode(',', $args[0]));
      } elseif (is_callable($args[0])) {
        $select = new Select($this);
        $args[0]($select);

        $this->addToSourceArray('DISTINCT', $select->getString());
      } else {
      }
    }

    return $this;
  }


  public function whereIn($name, array $list)
  {
    $query = $this->queryMakerIn($name, $list, '');
    $this->addOperator('AND');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }

  public function whereNotIn($name, array $list)
  {
    $query = $this->queryMakerIn($name, $list, 'NOT');
    $this->addOperator('AND');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }

  public function orWhereIn($name, array $list)
  {
    $query = $this->queryMakerIn($name, $list, '');
    $this->addOperator('OR');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }

  public function orWhereNotIn($name, array $list)
  {
    $query = $this->queryMakerIn($name, $list, 'NOT');
    $this->addOperator('OR');
    $this->addToSourceArray('WHERE', $query);
    return $this;
  }


  public function whereColumn($first, $operator, $second = false)
  {

    $this->addOperator('AND');
    $this->fix_operator_and_value($operator, $second);
    $this->addToSourceArray('WHERE', "`$first` $operator `$second`");

    return $this;
  }





  private function queryMakerIn($name, array $list, $extra_opration = '')
  {

    $name = $this->fix_column_name($name)['name'];

    $values = [];

    $this->method_in_maker($list, function ($get_param_name) use (&$values) {
      $values[] = $get_param_name;
    });

    $string_query_name = $name;

    if (!empty($extra_opration)) {
      $string_query_name .= ' ' . $extra_opration;
    }


    $string_query_value = 'IN(' . implode(',', $values) . ')';

    $string_query = "$string_query_name $string_query_value";

    return $string_query;
  }




  public function where(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhere($args);
    return $this;
  }

  public function orWhere(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhere($args);
    return $this;
  }

  public function whereNot(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhere($args, 'NOT');
    return $this;
  }

  public function orWhereNot(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhere($args, 'NOT');
    return $this;
  }



  public function whereNull($name)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereStaticValue($name, 'IS NULL');
    return $this;
  }

  public function orWhereNull($name)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereStaticValue($name, 'IS NULL');
    return $this;
  }

  public function whereNotNull($name)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereStaticValue($name, 'IS NOT NULL');
    return $this;
  }

  public function orWhereNotNull($name)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereStaticValue($name, 'IS NOT NULL');
    return $this;
  }


  public function whereBetween($name, array $values)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereBetween($name, $values);
    return $this;
  }

  public function orWhereBetween($name, array $values)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereBetween($name, $values);
    return $this;
  }

  public function whereNotBetween($name, array $values)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereBetween($name, $values, 'NOT');
    return $this;
  }

  public function orWhereNotBetween($name, array $values)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereBetween($name, $values, 'NOT');
    return $this;
  }



  public function whereDate(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('DATE', $args);
    return $this;
  }

  public function orWhereDate(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('DATE', $args);
    return $this;
  }

  public function whereYear(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('YEAR', $args);
    return $this;
  }

  public function orWhereYear(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('YEAR', $args);
    return $this;
  }

  public function whereMonth(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('MONTH', $args);
    return $this;
  }

  public function orWhereMonth(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('MONTH', $args);
    return $this;
  }


  public function whereDay(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('DAY', $args);
    return $this;
  }

  public function orWhereDay(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('DAY', $args);
    return $this;
  }

  public function whereTime(...$args)
  {
    $this->addOperator('AND');
    $this->queryMakerWhereLikeDate('TIME', $args);
    return $this;
  }

  public function orWhereTime(...$args)
  {
    $this->addOperator('OR');
    $this->queryMakerWhereLikeDate('TIME', $args);
    return $this;
  }



  public function join(...$args)
  {
    $query = $this->queryMakerJoin('INNER', $args);
    $this->addToSourceArray('JOIN', $query);
    return $this;
  }

  public function leftJoin(...$args)
  {
    $query = $this->queryMakerJoin('LEFT', $args);
    $this->addToSourceArray('JOIN', $query);
    return $this;
  }

  public function rightJoin(...$args)
  {
    $query = $this->queryMakerJoin('RIGHT', $args);
    $this->addToSourceArray('JOIN', $query);
    return $this;
  }

  public function fullJoin(...$args)
  {
    $query = $this->queryMakerJoin('FULL', $args);
    $this->addToSourceArray('JOIN', $query);
    return $this;
  }

  public function crossJoin($column)
  {
    $this->addToSourceArray('JOIN', "CROSS JOIN `$column`");
    return $this;
  }




  /**
   * Retrieve the "count" result of the query.
   *
   * @param  string  $columns
   * @return int
   */
  public function count($column = '*')
  {
    $this->select(function ($query) use ($column) {
      $query->count($column)->as('count');
    });

    return $this->first()->count;
  }

  /**
   * Retrieve the sum of the values of a given column.
   *
   * @param  string  $columns
   * @return int
   */
  public function sum($column = '*')
  {
    $this->select(function ($query) use ($column) {
      $query->sum($column)->as('sum');
    });

    return $this->first()->sum;
  }

  /**
   * Retrieve the average of the values of a given column.
   *
   * @param  string  $column
   * @return mixed
   */
  public function avg($column = '*')
  {
    $this->select(function ($query) use ($column) {
      $query->avg($column)->as('avg');
    });

    return $this->first()->avg;
  }

  /**
   * Retrieve the max of the values of a given column.
   *
   * @param  string  $column
   * @return mixed
   */
  public function max($column = '*')
  {
    $this->select(function ($query) use ($column) {
      $query->max($column)->as('max');
    });

    return $this->first()->max;
  }

  /**
   * Retrieve the min of the values of a given column.
   *
   * @param  string  $column
   * @return mixed
   */
  public function min($column = '*')
  {
    $this->select(function ($query) use ($column) {
      $query->min($column)->as('min');
    });

    return $this->first()->max;
  }


  /**
   * Set the "limit" value of the query.
   *
   * @param  int  $value
   * @return $this
   */
  public function limit(int $value)
  {
    $this->addToSourceArray('LIMIT', "LIMIT $value");
    return $this;
  }







  /**
   * Add a "having" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @param  string  $boolean
   * @return $this
   */
  public function having($column, $operator, $value = null, $boolean = 'and', $fn = '')
  {
    $this->addOperatorHaving($boolean);
    $this->fix_operator_and_value($operator, $value);
    $column = $this->fix_column_name($column)['name'];

    $array = $this->getSourceValueItem('HAVING');
    $beginning = 'HAVING';

    if (count($array) > 0) {
      $beginning = '';
    }

    if (empty($fn)) {
      $this->addToSourceArray('HAVING', "$beginning $column $operator $value");
    } else {
      $this->addToSourceArray('HAVING', "$beginning $fn($column) $operator $value");
    }

    return $this;
  }

  /**
   * Add a "or having" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return \Illuminate\Database\Query\Builder|static
   */
  public function orHaving($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'OR');
  }

  /**
   * Add a "having count()" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return $this
   */
  public function havingCount($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'AND', 'COUNT');
  }

  /**
   * Add a "having sum()" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return $this
   */
  public function havingSum($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'AND', 'SUM');
  }

  /**
   * Add a "having avg()" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return $this
   */
  public function havingAvg($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'AND', 'AVG');
  }

  /**
   * Add a "or having count()" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return $this
   */
  public function orHavingCount($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'OR', 'COUNT');
  }

  /**
   * Add a "or having sum()" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return $this
   */
  public function orHavingSum($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'OR', 'SUM');
  }

  /**
   * Add a "or having avg()" clause to the query.
   *
   * @param  string  $column
   * @param  string|null  $operator
   * @param  string|null  $value
   * @return $this
   */
  public function orHavingAvg($column, $operator, $value = null)
  {
    return $this->having($column, $operator, $value, 'OR', 'AVG');
  }






  /**
   * Add a "group by" clause to the query.
   *
   * @param  array  ...$groups
   * @return $this
   */
  public function groupBy(...$groups)
  {
    $arr = [];
    foreach ($groups as $group) {
      $arr[] = $this->fix_column_name($group)['name'];
    }
    $this->addToSourceArray('GROUP_BY', "GROUP BY " . implode(',', $arr));
    return $this;
  }


  /**
   * Add an "order by" clause to the query.
   *
   * @param  string  $column
   * @param  string  $direction
   * @return $this
   */
  public function orderBy($column, $direction = 'asc')
  {
    $column = $this->fix_column_name($column)['name'];
    $this->addToSourceArray('ORDER_BY', "ORDER BY $column $direction");
    return $this;
  }


  /**
   * Add an "order by count(`column`)" clause to the query.
   *
   * @param  string  $column
   * @param  string  $direction
   * @return $this
   */
  public function orderByCount($column, $direction = 'asc')
  {
    $column = $this->fix_column_name($column)['name'];
    $this->addToSourceArray('ORDER_BY', "ORDER BY COUNT($column) $direction");
    return $this;
  }


  public function latest(){
    $this->orderBy('id','DESC');
    return $this;
  }

  public function oldest(){
    $this->orderBy('id','ASC');
    return $this;
  }



  // public function chunk($count, callable $callback){

  // }




  private function queryMakerJoin($type, $args)
  {
    $join_table = $args[0];
    $join_table_column = $args[1];
    $operator = $args[2] ?? false;
    $main_column = $args[3] ?? false;

    if (!$operator && !$main_column) {
      $table_second = $this->fix_column_name($join_table);
      $table_main = $this->fix_column_name($join_table_column);

      $join_table = $table_second['table'];

      $join_table_column = $table_second['name'];

      $operator = '=';

      $main_column = $table_main['name'];
    } else if ($operator && !$main_column) {
      $table_second = $this->fix_column_name($join_table);
      $table_main = $this->fix_column_name($operator);

      $operator = $join_table_column;

      $join_table = $table_second['table'];
      $join_table_column = $table_second['name'];

      $main_column = $table_main['name'];
    } else if ($main_column) {
      $join_table = "`$join_table`";

      $join_table_column = $this->fix_column_name($join_table_column)['name'];
      $main_column = $this->fix_column_name($main_column)['name'];
    }

    return "$type JOIN $join_table ON $join_table_column $operator $main_column";
  }



  private function queryMakerWhereLikeDate($action, $args)
  {

    $column = $args[0];
    $operator = $args[1];
    $value = $args[2] ?? false;

    $this->fix_operator_and_value($operator, $value);

    $column = $this->fix_column_name($column)['name'];

    $value_name = $this->add_to_param_auto_name($column);


    $query = "$action($column) $operator $value_name";


    /*
      | Add finally string to Source
      */
    $this->addToSourceArray('WHERE', $query);
  }



  private function queryMakerWhereStaticValue($name, $value)
  {
    $name = $this->fix_column_name($name)['name'];

    $query = "$name $value";

    /*
    | Add NOT to query
    */
    if (!empty($extra_operation)) {
      $query = 'NOT ' . $query;
    }

    $this->addToSourceArray('WHERE', $query);
  }

  private function queryMakerWhereBetween($name, array $values, $extra_operation = '')
  {
    $name = $this->fix_column_name($name)['name'];

    $v1 = $this->add_to_param_auto_name($values[0]);
    $v2 = $this->add_to_param_auto_name($values[1]);

    $query = "$name BETWEEN $v1 AND $v2";

    /*
    | Add NOT to query
    */
    if (!empty($extra_operation)) {
      $query = 'NOT ' . $query;
    }

    $this->addToSourceArray('WHERE', $query);
  }

  private function queryMakerWhere($args, $extra_operation = '')
  {

    if (is_string($args[0])) {

      $column = $args[0];
      $operator = $args[1];
      $value = $args[2] ?? false;


      $this->fix_operator_and_value($operator, $value);

      $column = $this->fix_column_name($column)['name'];

      $value_name = $this->add_to_param_auto_name($value);


      $query = "$column $operator $value_name";

      /*
      | Add NOT to query
      */
      if (!empty($extra_operation)) {
        $query = 'NOT ' . $query;
      }

      /*
      | Add finally string to Source
      */
      $this->addToSourceArray('WHERE', $query);
    } else if (is_callable($args[0])) {

      $this->addStartParentheses();
      $args[0]($this);
      $this->addEndParentheses();
    }
  }

  public function makeSelectQueryString()
  {

    $this->addToSourceArray('SELECT', "SELECT");
    $this->addToSourceArray('FROM', "FROM `$this->TABLE`");

    if (count($this->getSourceValueItem('DISTINCT')) == 0) {
      $this->select('*');
    }


    ksort($this->SOURCE_VALUE);

    $array = [];
    foreach ($this->SOURCE_VALUE as $value) {
      if (is_array($value)) {
        $array[] = implode(' ', $value);
      }
    }

    return implode(' ', $array);
  }

  public function get()
  {
    $query = $this->makeSelectQueryString();
    echo "\n query : " . $query . "\n\n";
    echo json_encode($this->PARAMS) . "\n\n";
    // die;
    return $this->execute($query, $this->PARAMS, true);
  }


  public function first()
  {
    $array = $this->limit(1)->get();

    if (count($array) == 1) {
      return $array[0];
    }

    return false;
  }


  public function getSourceValueItem($struct_name)
  {
    $s_index = $this->sql_stractur($struct_name);
    return $this->SOURCE_VALUE[$s_index] ?? [];
  }

  public function addToSourceArray($struct_name, $value)
  {
    $s_index = $this->sql_stractur($struct_name);
    $this->SOURCE_VALUE[$s_index][] = $value;
  }
}
