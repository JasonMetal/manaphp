<?php
namespace ManaPHP\Model;

use ManaPHP\Component;
use ManaPHP\Exception\InvalidValueException;
use ManaPHP\Exception\NotImplementedException;

/**
 * Class ManaPHP\Model\Criteria
 *
 * @package ManaPHP\Model
 * @property \ManaPHP\Http\RequestInterface  $request
 * @property \ManaPHP\Model\Relation\Manager $relationsManager
 */
abstract class Criteria extends Component implements CriteriaInterface
{
    /**
     * @var \ManaPHP\Model
     */
    protected $_model;

    /**
     * @var bool
     */
    protected $_multiple;

    /**
     * @var array
     */
    protected $_with = [];

    /**
     * @var string|callable
     */
    protected $_index;

    /**
     * @return \ManaPHP\Model
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * Sets SELECT DISTINCT / SELECT ALL flag
     *
     * @param bool $distinct
     *
     * @return static
     */
    public function distinct($distinct = true)
    {
        throw new NotImplementedException(__METHOD__);
    }

    /**
     * @param array $filters
     *
     * @return static
     */
    public function whereSearch($filters)
    {
        $data = $this->request->get();

        $conditions = [];
        $fields = $this->_model->getFields();
        foreach ($filters as $k => $v) {
            preg_match('#^(\w+)(.*)$#', is_int($k) ? $v : $k, $match);
            $field = $match[1];

            if (!in_array($field, $fields, true)) {
                throw new InvalidValueException(['`:model` is not contains `:field` field', 'model' => get_declared_classes(), 'field' => $field]);
            }

            if (is_int($k)) {
                if (!isset($data[$field])) {
                    continue;
                }
                $value = $data[$field];
                if (is_string($value)) {
                    $value = trim($value);
                    if ($value === '') {
                        continue;
                    }
                }
                $conditions[$v] = $value;
            } else {
                $conditions[$k] = $v;
            }
        }

        return $this;
    }

    /**
     * @param string     $field
     * @param int|string $min
     * @param int|string $max
     *
     * @return static
     */
    public function whereDateBetween($field, $min, $max)
    {
        if ($min && strpos($min, ':') === false) {
            $min = (int)(is_numeric($min) ? $min : strtotime($min . ' 00:00:00'));
        }
        if ($max && strpos($max, ':') === false) {
            $max = (int)(is_numeric($max) ? $max : strtotime($max . ' 23:59:59'));
        }

        if ($format = $this->_model->getDateFormat($field)) {
            if (is_int($min)) {
                $min = date($format, $min);
            }
            if (is_int($max)) {
                $max = date($format, $max);
            }
        } else {
            if ($min && !is_int($min)) {
                $min = (int)strtotime($min);
            }
            if ($max && !is_int($max)) {
                $max = (int)strtotime($max);
            }
        }

        return $this->whereBetween($field, $min ?: null, $max ?: null);
    }

    /**
     * alias of whereBetween
     *
     * @param string           $expr
     * @param int|float|string $min
     * @param int|float|string $max
     *
     * @return static
     * @deprecated
     */
    public function betweenWhere($expr, $min, $max)
    {
        return $this->whereBetween($expr, $min, $max);
    }

    /**
     * alias of whereNotBetween
     *
     * @param string           $expr
     * @param int|float|string $min
     * @param int|float|string $max
     *
     * @return static
     * @deprecated
     */
    public function notBetweenWhere($expr, $min, $max)
    {
        return $this->whereNotBetween($expr, $min, $max);
    }

    /**
     * alias of whereIn
     *
     * @param string                           $expr
     * @param array|\ManaPHP\Db\QueryInterface $values
     *
     * @return static
     * @deprecated
     */
    public function inWhere($expr, $values)
    {
        return $this->whereIn($expr, $values);
    }

    /**
     * alias of whereNotIn
     *
     * @param string                           $expr
     * @param array|\ManaPHP\Db\QueryInterface $values
     *
     * @return static
     * @deprecated
     */
    public function notInWhere($expr, $values)
    {
        return $this->whereNotIn($expr, $values);
    }

    /**
     * @param string|array $with
     *
     * @return static
     */
    public function with($with)
    {
        if (is_string($with)) {
            if (strpos($with, ',') === false) {
                $with = [$with];
            } else {
                $with = (array)preg_split('#[\s,]+#', $with, -1, PREG_SPLIT_NO_EMPTY);
            }
        }

        $this->_with = $this->_with ? array_merge($this->_with, $with) : $with;

        return $this;
    }

    /**
     * @param int $size
     * @param int $page
     *
     * @return static
     */
    public function page($size = null, $page = null)
    {
        if ($size === null) {
            $size = $this->request->get('size', 'int', 10);
        }

        if ($page === null) {
            $page = $this->request->get('page', 'int', 1);
        }

        $this->limit($size, ($page - 1) * $size);

        return $this;
    }

    /**
     * @param bool $multiple
     *
     * @return static
     */
    public function setFetchType($multiple)
    {
        $this->_multiple = $multiple;

        return $this;
    }

    /**
     * @return array
     */
    abstract public function execute();

    /**
     * @param bool $asArray
     *
     * @return \ManaPHP\Model[]|\ManaPHP\Model|null|array
     */
    public function fetch($asArray = false)
    {
        if ($asArray) {
            $r = $this->execute();

            if ($this->_with) {
                $r = $this->relationsManager->earlyLoad($this->_model, $r, $this->_with);
            }

            return $r;
        }
        if ($this->_multiple === false) {
            $rs = $this->execute();
            if (isset($rs[0])) {
                $modelName = get_class($this->_model);
                $model = new $modelName($rs[0]);
                if ($this->_with) {
                    $this->relationsManager->lazyBindAll($model, $this->_with);
                }
                return $model;
            } else {
                return null;
            }
        } else {
            $modelName = get_class($this->_model);

            $models = [];
            foreach ($this->execute() as $k => $result) {
                $model = new $modelName($result);
                if ($this->_with) {
                    $this->relationsManager->lazyBindAll($model, $this->_with);
                }

                $models[$k] = $model;
            }

            return $models;
        }
    }

    /**
     * @param string $field
     *
     * @return int|float
     */
    public function sum($field)
    {
        return $this->aggregate(['r' => "SUM($field)"])[0]['r'];
    }

    /**
     * @param string $field
     *
     * @return int|float
     */
    public function max($field)
    {
        return $this->aggregate(['r' => "MAX($field)"])[0]['r'];
    }

    /**
     * @param string $field
     *
     * @return int|float
     */
    public function min($field)
    {
        return $this->aggregate(['r' => "MIN($field)"])[0]['r'];
    }

    /**
     * @param string $field
     *
     * @return double
     */
    public function avg($field)
    {
        return (double)$this->aggregate(['r' => "AVG($field)"])[0]['r'];
    }

    /**
     * @param string $field
     *
     * @return int
     */
    public function count($field = '*')
    {
        if ($rs = $this->aggregate(['r' => "COUNT($field)"])) {
            $r = $rs[0]['r'];
            return is_string($r) ? (int)$r : $r;
        } else {
            return 0;
        }
    }

    public function jsonSerialize()
    {
        return $this->fetch();
    }

    /**
     * @param array $options
     *
     * @return static
     */
    public function options($options)
    {
        if (!$options) {
            return $this;
        }

        if (isset($options['limit'])) {
            $this->limit($options['limit'], isset($options['offset']) ? $options['offset'] : 0);
        } elseif (isset($options['size'])) {
            $this->page($options['size'], isset($options['page']) ? $options['page'] : null);
        }

        if (isset($options['distinct'])) {
            $this->distinct($options['distinct']);
        }

        if (isset($options['order'])) {
            $this->orderBy($options['order']);
        }

        if (isset($options['index'])) {
            $this->indexBy($options['index']);
        }

        if (isset($options['with'])) {
            $this->with($options['with']);
        }

        if (isset($options['group'])) {
            $this->groupBy($options['group']);
        }

        return $this;
    }
}