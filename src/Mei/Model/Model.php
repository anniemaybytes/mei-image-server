<?php declare(strict_types=1);

namespace Mei\Model;

use Exception;
use InvalidArgumentException;
use Mei\Cache\IKeyStore;
use Mei\Entity\IEntity;
use Mei\Utilities\PDOParamMapper;
use PDO;

/**
 * Class Model
 *
 * @package Mei\Model
 */
abstract class Model implements IModel
{
    /**
     * @var PDO
     */
    protected $db;

    /**
     * @var IKeyStore
     */
    protected $cache;

    /**
     * callable that takes an ICacheable as argument and returns an entity
     */
    protected $entityBuilder;

    /**
     * @var bool $inTransaction
     */
    protected $inTransaction;

    /**
     * Model constructor.
     *
     * @param callable $entityBuilder
     * @param IKeyStore $cache
     * @param PDO $db
     */
    public function __construct(callable $entityBuilder, IKeyStore $cache, PDO $db)
    {
        $this->entityBuilder = $entityBuilder;
        $this->inTransaction = false;
        $this->cache = $cache;
        $this->db = $db;
    }

    /**
     * Return an instance of the database object.
     *
     * @return PDO
     */
    protected function getDatabase(): PDO
    {
        return $this->db;
    }

    /**
     * Return an instance of the cache object.
     *
     * @return IKeyStore
     */
    protected function getCache(): IKeyStore
    {
        return $this->cache;
    }

    /**
     * Get the name of the table where entities are stored.
     *
     * @return string
     */
    abstract public function getTableName();

    // needs to be run immediately after a SELECT SQL_CALC_FOUND_ROWS statement

    /**
     * @return int
     * @throws Exception
     */
    public function getFoundRows(): int
    {
        $q = $this->getDatabase()->query('SELECT FOUND_ROWS()');
        return (int)$q->fetchColumn();
    }

    /**
     * @param array|null $ids
     *
     * @return array
     */
    public function getEntitiesFromIds(?array $ids)
    {
        if (!$ids) {
            return [];
        }
        return array_map(
            function ($id) {
                return $this->getById($id);
            },
            $ids
        );
    }

    /**
     * @param array|null $id
     *
     * @return mixed|IEntity
     * @see \Mei\Model\IModel::getById()
     */
    public function getById(?array $id)
    {
        if (is_null($id) || $id === [] || !(is_array($id))) {
            return null;
        }

        $builder = $this->entityBuilder;

        $table = $this->getTableName();

        $entityCache = $this->getCache()->getEntityCache($table, $id);

        // ignore cache if in the middle of a transaction
        if ($this->inTransaction) {
            $row = null;
        } else {
            $row = $entityCache->getRow();
        }
        if (!$row) {
            $whereStr = implode(
                ' AND ',
                array_map(
                    function ($col) {
                        return "`$col` = :$col";
                    },
                    array_keys($id)
                )
            );

            $attrs = $builder($entityCache)->getAttributes(); // we need to create mockup of entity first

            // note that when searching for strings, mysql is case-insensitive by
            // default; you can force a search to be case sensitive by for example using
            // select * from table where column like 'value' COLLATE utf8_bin
            $query = "SELECT * FROM `$table` WHERE $whereStr";
            $q = $this->getDatabase()->prepare($query);
            foreach ($id as $param => $value) {
                $q->bindValue(':' . $param, $value, PDOParamMapper::map($attrs[$param]));
            }
            $q->execute();
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $entityCache = $entityCache->setRow($row);
                $entityCache->save($this->getCache());
            } else {
                return null;
            }
        }

        return $builder($entityCache);
    }

    /**
     * @param array $arr
     *
     * @return mixed|IEntity
     * @see \Mei\Model\IModel::createEntity()
     */
    public function createEntity(array $arr)
    {
        if (!is_array($arr)) {
            throw new InvalidArgumentException("createEntity expects array as argument");
        }
        $builder = $this->entityBuilder;
        $entityCache = $this->getCache()->getEntityCache($this->getTableName());
        /** @var IEntity $entity */
        $entity = $builder($entityCache);

        foreach ($arr as $k => $v) {
            $entity->$k = $v;
        }

        $entity->reset($entityCache);
        $entity->setNew(true);
        return $entity;
    }

    /**
     * @param IEntity $entity
     *
     * @return IEntity
     * @see \Mei\Model\IModel::save()
     */
    public function save(IEntity $entity)
    {
        $table = $this->getTableName();
        $entity = clone $entity;

        if ($entity->isNew()) {
            $idAttr = $entity->getIdAttributes();

            // if there are multiple primary keys, require that both are set before
            // saving; otherwise, there is no way to identify the entity after insert
            if (count($idAttr) > 1) {
                $id = $entity->getId();
                if (is_null($id) || count($id) != count($idAttr)) {
                    throw new InvalidArgumentException("Unable to save entity - primary key not set");
                }
            }

            // note that getValues only returns values that are set
            // e.g. if ID is not set, no entry will exist for that key
            $values = $entity->getValues();
            $attrs = $entity->getAttributes();
            $cols = $vals = '';
            if ($values) {
                $cols = '`' . implode('`, `', array_keys($values)) . '`';
                $vals = ':' . implode(', :', array_keys($values));
            }

            // this query is fine even if $cols/$vals are empty
            $sql = "INSERT INTO `$table` ($cols) VALUES ($vals)";
            $q = $this->getDatabase()->prepare($sql);
            foreach ($values as $param => $value) {
                $q->bindValue(':' . $param, $value, PDOParamMapper::map($attrs[$param]));
            }
            $q->execute();

            $cache = $entity->getCacheable();

            // if using autoincrement id
            if (count($idAttr) == 1) {
                $insertId = $this->getDatabase()->lastInsertId();
                if (is_null($insertId)) {
                    throw new InvalidArgumentException("Unable to save entity - failed to retrieve id after save");
                }
                $idCol = reset($idAttr);
                $id = [$idCol => $insertId];
                $cache->setId($id);
            }

            // delete anything that might have been in cache and retrieve what we just saved
            $cache->delete($this->getCache());

            // note that it is possible the entity has no IDs, thus it is impossible
            // to retrieve the entity that just got inserted
            if (isset($id)) {
                return $this->getById($id);
            }

            return $entity;
        } else { // the entity is an old entity getting updated
            // nothing to change - return entity as is
            if (!$entity->hasChanged()) {
                return $entity;
            }

            $id = $entity->getId();
            $idAttr = $entity->getIdAttributes();
            if ((is_null($id)) || (count($id) == 0) || (count($id) != count($idAttr))) {
                throw new InvalidArgumentException("Unable to save entity - primary key not set");
            }

            $values = $entity->getChangedValues();
            $attrs = $entity->getAttributes();

            if (count($values) == 0) {
                throw new InvalidArgumentException(
                    'Unable to save entity - nothing was changed, but marked as changed'
                );
            }

            // prevent changing primary key, since this could result in overwriting
            // other entities (rather than saving the current one)
            // also check that each of the ID columns is set
            foreach ($idAttr as $idAttribute) {
                if (array_key_exists($idAttribute, $values)) {
                    throw new InvalidArgumentException("Unable to save entity - primary key was changed");
                }
                if (!array_key_exists($idAttribute, $id)) {
                    throw new InvalidArgumentException("Unable to save entity - primary key not set");
                }
            }

            $sql = "UPDATE `$table` SET ";

            // there must be changed values if we reached here
            $cols = array_keys($values);
            $cols = array_map(
                function ($col) {
                    return "`$col` = :$col";
                },
                $cols
            );
            $sql .= implode(', ', $cols);

            $where = array_map(
                function ($col) {
                    return "`$col` = :$col";
                },
                $idAttr
            );
            $where = implode(' AND ', $where);

            $sql .= " WHERE $where LIMIT 1";

            // add id columns for the query execution
            $values = array_merge($values, $id);

            $q = $this->getDatabase()->prepare($sql);
            foreach ($values as $param => $value) {
                $q->bindValue(':' . $param, $value, PDOParamMapper::map($attrs[$param]));
            }
            $q->execute();

            // delete anything that might have been in cache and retrieve what we just saved
            $cache = $entity->getCacheable();
            $cache->delete($this->getCache());
            return $this->getById($id);
        }
    }

    /**
     * @param IEntity $entity
     *
     * @return IEntity
     * @see \Mei\Model\IModel::delete()
     */
    public function delete(IEntity $entity)
    {
        $table = $this->getTableName();
        $entity = clone $entity;

        $id = $entity->getId();
        if (is_null($id) || count($id) == 0) {
            throw new InvalidArgumentException("Unable to delete entity - primary key not set");
        }

        $where = [];
        foreach (array_keys($id) as $k) {
            $where[] = "`$k` = :$k";
        }
        $where = implode(' AND ', $where);

        $attrs = $entity->getAttributes();

        $sql = "DELETE FROM `$table` WHERE $where LIMIT 1";
        $q = $this->getDatabase()->prepare($sql);
        foreach ($id as $param => $value) {
            $q->bindValue(':' . $param, $value, PDOParamMapper::map($attrs[$param]));
        }
        $q->execute();
        $cache = $entity->getCacheable();
        $cache->delete($this->getCache());
        return $entity;
    }

    /**
     * @param array $id
     *
     * @return IEntity|mixed
     */
    public function deleteById(array $id)
    {
        return $this->delete($this->getById($id));
    }

    /**
     * @return bool|int
     */
    public function beginTransaction()
    {
        // if we indicate we want to use a transaction, stop using cache
        $this->inTransaction = true;
        return $this->getDatabase()->beginTransaction();
    }

    /**
     * @return bool
     */
    public function commit(): bool
    {
        $this->inTransaction = false;
        return $this->getDatabase()->commit();
    }

    /**
     * @return bool|int
     */
    public function rollBack()
    {
        $this->inTransaction = false;
        return $this->getDatabase()->rollBack();
    }
}
