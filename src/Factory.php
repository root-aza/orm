<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Treap;

use Spiral\Core\Container;
use Spiral\Core\FactoryInterface as CoreFactory;
use Spiral\Treap\Config\RelationConfig;
use Spiral\Treap\Exception\FactoryException;

class Factory implements FactoryInterface
{
    /** @var RelationConfig */
    private $config;

    /** @var CoreFactory */
    private $factory;

    /** @var ORMInterface */
    private $orm;

    /** @var SchemaInterface */
    private $schema;

    /** @var MapperInterface[] */
    private $mappers = [];

    /** @var RelationInterface[] */
    private $relations = [];

    /**
     * @param RelationConfig   $config
     * @param CoreFactory|null $factory
     */
    public function __construct(RelationConfig $config, CoreFactory $factory = null)
    {
        $this->config = $config;
        $this->factory = $factory ?? new Container();
    }

    /**
     * @inheritdoc
     */
    public function withContext(ORMInterface $orm, SchemaInterface $schema): FactoryInterface
    {
        $factory = clone $this;
        $factory->orm = $orm;
        $factory->schema = $schema;
        $factory->mappers = [];
        $factory->relations = [];

        return $factory;
    }

    /**
     * @inheritdoc
     */
    public function mapper(string $class): MapperInterface
    {
        if (isset($this->mappers[$class])) {
            return $this->mappers[$class];
        }

        return $this->mappers[$class] = $this->factory->make(
            $this->getSchema()->define($class, Schema::MAPPER),
            [
                'orm'    => $this->orm,
                'class'  => $class,
                'schema' => $this->getSchema()->define($class, Schema::SCHEMA)
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function selector(string $class)
    {
        return new Selector($this->orm, $class);
    }

    /**
     * @inheritdoc
     */
    public function loader(string $class, string $relation): LoaderInterface
    {
        $schema = $this->getSchema()->defineRelation($class, $relation);

        return $this->config->getLoader($schema[RelationInterface::TYPE])->resolve(
            $this->factory,
            [
                'orm'      => $this->orm,
                'relation' => $relation,
                'class'    => $schema[RelationInterface::TARGET],
                'schema'   => $schema[RelationInterface::SCHEMA]
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function relation(string $class, string $relation): RelationInterface
    {
        $schema = $this->getSchema()->defineRelation($class, $relation);
        $type = $schema[RelationInterface::TYPE];

        if (isset($this->relations[$type])) {
            return $this->relations[$type];
        }

        return $this->relations[$type] = $this->config->getRelation($type)->resolve(
            $this->factory,
            [
                'orm'      => $this->orm,
                'relation' => $relation,
                'class'    => $schema[RelationInterface::TARGET],
                'schema'   => $schema[RelationInterface::SCHEMA]
            ]
        );
    }

    /**
     * @return SchemaInterface
     *
     * @throws FactoryException
     */
    protected function getSchema(): SchemaInterface
    {
        if (empty($this->schema)) {
            throw new FactoryException("Factory does not have associated schema");
        }

        return $this->schema;
    }
}