<?php
declare(strict_types=1);

namespace Dhii\PdoQuery;

use Dhii\Collection\CountableListInterface;
use Dhii\Collection\CountableMapInterface;
use Dhii\Collection\MapFactoryInterface;
use Dhii\Collection\MapInterface;
use Iterator;

/**
 * A collection of rows.
 *
 * Each row is a {@see CountableMapInterface}.
 */
class ResultSet implements
    Iterator,
    CountableListInterface
{
    /** @var array */
    protected $rows;
    /** @var MapFactoryInterface */
    protected $mapFactory;
    /** @var int */
    protected $currentIndex;

    public function __construct(array $rows, MapFactoryInterface $mapFactory)
    {
        $this->rows = $rows;
        $this->mapFactory = $mapFactory;
    }

    /**
     * @inheritDoc
     */
    public function count()
    {
        return count($this->rows);
    }

    /**
     * @inheritDoc
     */
    public function current(): MapInterface
    {
        return $this->createMap($this->rows[$this->currentIndex]);
    }

    /**
     * @inheritDoc
     */
    public function next()
    {
        $this->currentIndex++;
    }

    /**
     * @inheritDoc
     */
    public function key()
    {
        return $this->currentIndex;
    }

    /**
     * @inheritDoc
     */
    public function valid()
    {
        return array_key_exists($this->currentIndex, $this->rows);
    }

    /**
     * @inheritDoc
     */
    public function rewind()
    {
        $this->currentIndex = 0;
    }

    /**
     * @inheritDoc
     */
    protected function createMap(array $fields): MapInterface
    {
        return $this->mapFactory->createContainerFromArray($fields);
    }
}
