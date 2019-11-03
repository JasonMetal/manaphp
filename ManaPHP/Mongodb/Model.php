<?php

namespace ManaPHP\Mongodb;

use ManaPHP\Di;
use ManaPHP\Exception\InvalidValueException;
use ManaPHP\Exception\NotImplementedException;
use ManaPHP\Exception\RuntimeException;
use ManaPHP\Model\ExpressionInterface;
use MongoDB\BSON\ObjectId;

/**
 * Class ManaPHP\Mongodb\Model
 *
 * @package model
 *
 */
class Model extends \ManaPHP\Model
{
    /**
     * @var bool
     */
    protected static $_defaultAllowNullValue = false;

    /**
     * @var \MongoDB\BSON\ObjectId
     */
    public $_id;

    /**
     * Gets the connection used to crud data to the model
     *
     * @return string
     */
    public function getDb()
    {
        return 'mongodb';
    }

    /**
     * @param bool $allow
     */
    public static function setDefaultAllowNullValue($allow)
    {
        self::$_defaultAllowNullValue = $allow;
    }

    /**
     * @param mixed $context
     *
     * @return \ManaPHP\MongodbInterface
     */
    public static function connection($context = null)
    {
        list($db) = static::sample()->getUniqueShard($context);
        return Di::getDefault()->getShared($db);
    }

    /**
     * @return string =array_keys(get_object_vars(new static))[$i]
     */
    public function getPrimaryKey()
    {
        static $cached = [];

        $class = static::class;

        if (!isset($cached[$class])) {
            $fields = $this->getFields();

            if (in_array('id', $fields, true)) {
                return $cached[$class] = 'id';
            }

            $tryField = lcfirst(($pos = strrpos($class, '\\')) === false ? $class : substr($class, $pos + 1)) . '_id';
            if (in_array($tryField, $fields, true)) {
                return $cached[$class] = $tryField;
            }

            $source = $this->getSource();
            if (($pos = strpos($source, ':')) !== false) {
                $collection = substr($source, 0, $pos);
            } elseif (($pos = strpos($source, ',')) !== false) {
                $collection = substr($source, 0, $pos);
            } else {
                $collection = $source;
            }

            $tryField = (($pos = strpos($collection, '.')) ? substr($collection, $pos + 1) : $collection) . '_id';
            if (in_array($tryField, $fields, true)) {
                return $cached[$class] = $tryField;
            }

            throw new NotImplementedException(['Primary key of `:model` model can not be inferred', 'model' => $class]);
        }

        return $cached[$class];
    }

    /**
     * @return array =get_object_vars(new static)
     */
    public function getFields()
    {
        static $cached = [];

        $class = static::class;

        if (!isset($cached[$class])) {
            $fieldTypes = $this->getFieldTypes();
            if (isset($fieldTypes['_id']) && $fieldTypes['_id'] === 'objectid') {
                unset($fieldTypes['_id']);
            }
            return $cached[$class] = array_keys($fieldTypes);
        }

        return $cached[$class];
    }

    /**
     * @return array =get_object_vars(new static)
     */
    public function getIntFields()
    {
        static $cached = [];

        $class = static::class;

        if (!isset($cached[$class])) {
            $fields = [];
            foreach ($this->getFieldTypes() as $field => $type) {
                if ($type === 'int') {
                    $fields[] = $field;
                }
            }

            return $cached[$class] = $fields;
        }

        return $cached[$class];
    }

    /**
     * bool, int, float, string, array, objectid
     *
     * @return array =array_keys(get_object_vars(new static))
     */
    public function getFieldTypes()
    {
        static $cached = [];

        $class = static::class;

        if (!isset($cached[$class])) {
            list($db, $collection) = $this->getAnyShard();

            /** @var \ManaPHP\MongodbInterface $mongodb */
            $mongodb = $this->_di->getShared($db);
            if (!$docs = $mongodb->fetchAll($collection, [], ['limit' => 1])) {
                throw new RuntimeException(['`:collection` collection has none record', 'collection' => $collection]);
            }

            $types = [];
            foreach ($docs[0] as $field => $value) {
                $type = gettype($value);
                if ($type === 'integer') {
                    $types[$field] = 'int';
                } elseif ($type === 'string') {
                    $types[$field] = 'string';
                } elseif ($type === 'double') {
                    $types[$field] = 'float';
                } elseif ($type === 'boolean') {
                    $types[$field] = 'bool';
                } elseif ($type === 'array') {
                    $types[$field] = 'array';
                } elseif ($value instanceof ObjectId) {
                    if ($field === '_id') {
                        continue;
                    }
                    $types[$field] = 'objectid';
                } else {
                    throw new RuntimeException(['`:field` field value type can not be infer.', 'field' => $field]);
                }
            }

            $cached[$class] = $types;
        }

        return $cached[$class];
    }

    /**
     * @return bool
     */
    public function isAllowNullValue()
    {
        return self::$_defaultAllowNullValue;
    }

    /**
     * @param \ManaPHP\MongodbInterface $mongodb
     * @param string                    $source
     *
     * @return bool
     */
    protected function _createAutoIncrementIndex($mongodb, $source)
    {
        $autoIncField = $this->getAutoIncrementField();

        if ($pos = strpos($source, '.')) {
            $db = substr($source, 0, $pos);
            $collection = substr($source, $pos + 1);
        } else {
            $db = null;
            $collection = $source;
        }

        $command = [
            'createIndexes' => $collection,
            'indexes' => [
                [
                    'key' => [
                        $autoIncField => 1
                    ],
                    'unique' => true,
                    'name' => $autoIncField
                ]
            ]
        ];

        $mongodb->command($command, $db);

        return true;
    }

    /**
     * @param int $step
     *
     * @return int
     */
    public function getNextAutoIncrementId($step = 1)
    {
        list($db, $source) = $this->getUniqueShard($this);

        /** @var \ManaPHP\MongodbInterface $mongodb */
        $mongodb = $this->_di->getShared($db);

        if ($pos = strpos($source, '.')) {
            $db = substr($source, 0, $pos);
            $collection = substr($source, $pos + 1);
        } else {
            $db = null;
            $collection = $source;
        }

        $command = [
            'findAndModify' => 'auto_increment_id',
            'query' => ['_id' => $collection],
            'update' => ['$inc' => ['current_id' => $step]],
            'new' => true,
            'upsert' => true
        ];

        $id = $mongodb->command($command, $db)[0]['value']['current_id'];

        if ($id === $step) {
            $this->_createAutoIncrementIndex($mongodb, $source);
        }

        return $id;
    }

    /**
     * @param string $type
     * @param mixed  $value
     *
     * @return bool|float|int|string|array|\MongoDB\BSON\ObjectID|\MongoDB\BSON\UTCDateTime
     */
    public function normalizeValue($type, $value)
    {
        if ($value === null) {
            return null;
        }

        if ($type === 'string') {
            return is_string($value) ? $value : (string)$value;
        } elseif ($type === 'int') {
            return is_int($value) ? $value : (int)$value;
        } elseif ($type === 'float') {
            return is_float($value) ? $value : (float)$value;
        } elseif ($type === 'objectid') {
            return is_scalar($type) ? new ObjectID($value) : $value;
        } elseif ($type === 'bool') {
            return is_bool($value) ? $value : (bool)$value;
        } elseif ($type === 'array') {
            return (array)$value;
        } else {
            throw new InvalidValueException(['`:model` model is not supported `:type` type', 'model' => static::class, 'type' => $type]);
        }
    }

    /**
     * @return \ManaPHP\Mongodb\Query|\ManaPHP\QueryInterface
     */
    public function newQuery()
    {
        return $this->_di->get('ManaPHP\Mongodb\Query')->setModel($this);
    }

    /**
     * @param string $alias
     *
     * @return \ManaPHP\Mongodb\Query
     */
    public static function query($alias = null)
    {
        return static::sample()->newQuery();
    }

    /**
     * @return static
     */
    public function create()
    {
        $autoIncrementField = $this->getAutoIncrementField();
        if ($autoIncrementField && $this->$autoIncrementField === null) {
            $this->$autoIncrementField = $this->getNextAutoIncrementId();
        }

        $fields = $this->getFields();
        foreach ($this->getAutoFilledData(self::OP_CREATE) as $field => $value) {
            /** @noinspection NotOptimalIfConditionsInspection */
            if (!in_array($field, $fields, true) || $this->$field !== null) {
                continue;
            }
            $this->$field = $value;
        }

        $this->validate($fields);

        if ($this->_id) {
            if (is_string($this->_id) && strlen($this->_id) === 24) {
                $this->_id = new ObjectID($this->_id);
            }
        } else {
            $this->_id = new ObjectID();
        }

        $allowNull = $this->isAllowNullValue();
        foreach ($this->getFieldTypes() as $field => $type) {
            if ($field === '_id') {
                continue;
            }

            if ($this->$field !== null) {
                if (is_scalar($this->$field)) {
                    $this->$field = $this->normalizeValue($type, $this->$field);
                }
            } else {
                $this->$field = $allowNull ? null : $this->normalizeValue($type, '');
            }
        }

        list($db, $collection) = $this->getUniqueShard($this);

        $this->fireEvent('model:saving');
        $this->fireEvent('model:creating');

        $fieldValues = [];
        foreach ($fields as $field) {
            $fieldValues[$field] = $this->$field;
        }

        $fieldValues['_id'] = $this->_id;

        foreach ($this->getJsonFields() as $field) {
            if (is_array($this->$field)) {
                $fieldValues[$field] = json_stringify($this->$field);
            }
        }

        /** @var \ManaPHP\MongodbInterface $mongodb */
        $mongodb = $this->_di->getShared($db);
        $mongodb->insert($collection, $fieldValues);

        $this->fireEvent('model:created');
        $this->fireEvent('model:saved');

        $this->_snapshot = $this->toArray();

        return $this;
    }

    /**
     * Updates a model instance. If the instance does n't exist in the persistence it will throw an exception
     *
     * @return static
     */
    public function update()
    {
        $snapshot = $this->_snapshot;

        $primaryKey = $this->getPrimaryKey();

        $fieldTypes = $this->getFieldTypes();
        $fields = array_keys($fieldTypes);

        $changedFields = [];
        foreach ($fields as $field) {
            if ($this->$field === null) {
                /** @noinspection NotOptimalIfConditionsInspection */
                if (isset($snapshot[$field])) {
                    $changedFields[] = $field;
                }
            } elseif (!isset($snapshot[$field])) {
                if (is_scalar($this->$field)) {
                    $this->$field = $this->normalizeValue($fieldTypes[$field], $this->$field);
                }
                $changedFields[] = $field;
            } elseif ($snapshot[$field] !== $this->$field) {
                if (is_scalar($this->$field)) {
                    $this->$field = $this->normalizeValue($fieldTypes[$field], $this->$field);
                }

                /** @noinspection NotOptimalIfConditionsInspection */
                if ($snapshot[$field] !== $this->$field) {
                    $changedFields[] = $field;
                }
            }
        }

        if (!$changedFields) {
            return $this;
        }

        $this->validate($changedFields);

        foreach ($this->getAutoFilledData(self::OP_UPDATE) as $field => $value) {
            if (in_array($field, $fields, true)) {
                $this->$field = $value;
            }
        }

        list($db, $collection) = $this->getUniqueShard($this);

        $this->fireEvent('model:saving');
        $this->fireEvent('model:updating');

        $fieldValues = [];
        foreach ($fields as $field) {
            if ($this->$field === null) {
                if (isset($snapshot[$field])) {
                    $fieldValues[$field] = null;
                }
            } elseif (!isset($snapshot[$field]) || $snapshot[$field] !== $this->$field) {
                $fieldValues[$field] = $this->$field;
            }
        }

        unset($fieldValues[$primaryKey]);

        if (!$fieldValues) {
            return $this;
        }

        foreach ($this->getJsonFields() as $field) {
            if (isset($fieldValues[$field]) && is_array($fieldValues[$field])) {
                $fieldValues[$field] = json_stringify($fieldValues[$field]);
            }
        }

        /** @var \ManaPHP\MongodbInterface $mongodb */
        $mongodb = $this->_di->getShared($db);
        $mongodb->update($collection, $fieldValues, [$primaryKey => $this->$primaryKey]);

        $expressionFields = [];
        foreach ($fieldValues as $field => $value) {
            if ($value instanceof ExpressionInterface) {
                $expressionFields[] = $field;
            }
        }

        if ($expressionFields) {
            $expressionFields['_id'] = false;
            if ($rs = $this->newQuery()->where([$primaryKey => $this->$primaryKey])->select($expressionFields)->execute()) {
                foreach ((array)$rs[0] as $field => $value) {
                    $this->$field = $value;
                }
            }
        }

        $this->fireEvent('model:updated');
        $this->fireEvent('model:saved');

        $this->_snapshot = $this->toArray();

        return $this;
    }

    /**
     * Deletes a model instance. Returning true on success or false otherwise.
     *
     * @return static
     */
    public function delete()
    {
        list($db, $collection) = $this->getUniqueShard($this);

        $this->fireEvent('model:deleting');

        /** @var \ManaPHP\MongodbInterface */
        $connection = $this->_di->getShared($db);
        $primaryKey = $this->getPrimaryKey();

        $connection->delete($collection, [$primaryKey => $this->$primaryKey]);

        $this->fireEvent('model:deleted');

        return $this;
    }

    /**
     * @param array $pipeline
     * @param array $options
     *
     * @return array
     */
    public static function aggregateEx($pipeline, $options = [])
    {
        $sample = static::sample();

        list($db, $collection) = $sample->getUniqueShard();

        /** @var \ManaPHP\MongodbInterface $mongodb */
        $mongodb = Di::getDefault()->getShared($db);
        return $mongodb->aggregate($collection, $pipeline, $options);
    }

    /**
     * @param array[] $documents
     *
     * @return int
     */
    public static function bulkInsert($documents)
    {
        if (!$documents) {
            return 0;
        }

        $sample = static::sample();

        $autoIncrementField = $sample->getAutoIncrementField();
        $allowNull = $sample->isAllowNullValue();
        $fieldTypes = $sample->getFieldTypes();
        foreach ($documents as $i => $document) {
            if ($autoIncrementField && !isset($document[$autoIncrementField])) {
                $document[$autoIncrementField] = $sample->getNextAutoIncrementId();
            }
            foreach ($fieldTypes as $field => $type) {
                if (isset($document[$field])) {
                    $document[$field] = $sample->normalizeValue($type, $document[$field]);
                } elseif ($field !== '_id') {
                    $document[$field] = $allowNull ? null : $sample->normalizeValue($type, '');
                }
            }
            $documents[$i] = $document;
        }

        list($db, $collection) = $sample->getUniqueShard();

        /** @var \ManaPHP\MongodbInterface $mongodb */
        $mongodb = Di::getDefault()->getShared($db);
        return $mongodb->bulkInsert($collection, $documents);
    }

    /**
     * @param array $documents
     *
     * @return int
     */
    public static function bulkUpdate($documents)
    {
        if (!$documents) {
            return 0;
        }

        $sample = static::sample();

        $primaryKey = $sample->getPrimaryKey();
        $allowNull = $sample->isAllowNullValue();
        $fieldTypes = $sample->getFieldTypes();
        foreach ($documents as $i => $document) {
            if (!isset($document[$primaryKey])) {
                throw new InvalidValueException(['bulkUpdate `:model` model must set primary value', 'model' => static::class]);
            }
            foreach ((array)$document as $field => $value) {
                if ($value === null) {
                    $document[$field] = $allowNull ? null : $sample->normalizeValue($fieldTypes[$field], '');
                } else {
                    $document[$field] = $sample->normalizeValue($fieldTypes[$field], $value);
                }
            }
        }

        $shards = $sample->getMultipleShards();

        $affected_count = 0;
        foreach ($shards as $db => $collections) {
            /** @var \ManaPHP\MongodbInterface $mongodb */
            $mongodb = Di::getDefault()->getShared($db);
            foreach ($collections as $collection) {
                $affected_count += $mongodb->bulkUpdate($collection, $documents, $primaryKey);
            }
        }

        return $affected_count;
    }

    /**
     * @param array[] $documents
     *
     * @return int
     */
    public static function bulkUpsert($documents)
    {
        if (!$documents) {
            return 0;
        }

        $sample = static::sample();

        $primaryKey = $sample->getPrimaryKey();
        $allowNull = $sample->isAllowNullValue();
        $fieldTypes = $sample->getFieldTypes();
        foreach ($documents as $i => $document) {
            if (!isset($document[$primaryKey])) {
                $document[$primaryKey] = $sample->getNextAutoIncrementId();
            }
            foreach ($fieldTypes as $field => $type) {
                if (isset($document[$field])) {
                    $document[$field] = $sample->normalizeValue($type, $document[$field]);
                } elseif ($field !== '_id') {
                    $document[$field] = $allowNull ? null : $sample->normalizeValue($type, '');
                }
            }
            $documents[$i] = $document;
        }

        list($db, $collection) = $sample->getUniqueShard();

        /** @var \ManaPHP\MongodbInterface $mongodb */
        $mongodb = Di::getDefault()->getShared($db);
        return $mongodb->bulkUpsert($collection, $documents, $primaryKey);
    }

    /**
     * @param array $document
     *
     * @return int
     */
    public static function insert($document)
    {
        $sample = static::sample();

        $allowNull = $sample->isAllowNullValue();
        $fieldTypes = $sample->getFieldTypes();
        $autoIncrementField = $sample->getAutoIncrementField();
        if ($autoIncrementField && !isset($document[$autoIncrementField])) {
            $document[$autoIncrementField] = $sample->getNextAutoIncrementId();
        }

        foreach ($fieldTypes as $field => $type) {
            if (isset($document[$field])) {
                $document[$field] = $sample->normalizeValue($type, $document[$field]);
            } elseif ($field !== '_id') {
                $document[$field] = $allowNull ? null : $sample->normalizeValue($type, '');
            }
        }

        list($db, $collection) = $sample->getUniqueShard($document);

        /** @var \ManaPHP\MongodbInterface $mongodb */
        $mongodb = Di::getDefault()->getShared($db);
        $mongodb->insert($collection, $document);

        return 1;
    }

    /**
     * @param int|string|array $filters =get_object_vars(new static)
     *
     * @return \ManaPHP\Mongodb\Query|\ManaPHP\QueryInterface
     */
    public static function where($filters)
    {
        return static::select()->where(is_scalar($filters) ? [static::sample()->getPrimaryKey() => $filters] : $filters);
    }

    /**
     * @param array $filters =get_object_vars(new static)
     *
     * @return \ManaPHP\Mongodb\Query|\ManaPHP\QueryInterface
     */
    public static function search($filters)
    {
        return static::select()->search($filters);
    }

    public function __debugInfo()
    {
        $data = parent::__debugInfo();
        if ($data['_id'] === null) {
            unset($data['_id']);
        } elseif (is_object($data['_id'])) {
            $data['_id'] = (string)$data['_id'];
        }

        return $data;
    }
}