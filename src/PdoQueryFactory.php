<?php
declare(strict_types=1);

namespace Dhii\PdoQuery;

use Dhii\Collection\MapFactoryInterface;
use Dhii\Query\QueryInterface;
use Dhii\Query\StringQueryFactoryInterface;
use PDO;
use PDOStatement;
use RuntimeException;

/**
 * A factory of {@see PdoQuery} objects.
 *
 * Every call to {@see query()} will create a new query, which allows this class to
 * leverage the power of {@link PDOStatement prepared statements}.
 */
class PdoQueryFactory implements StringQueryFactoryInterface
{
    /**
     * @var PDO
     */
    protected $pdo;
    /**
     * @var MapFactoryInterface
     */
    protected $mapFactory;

    /**
     * @param PDO $pdo The DB connection.
     * @param MapFactoryInterface $mapFactory The factory used to create rows.
     */
    public function __construct(PDO $pdo, MapFactoryInterface $mapFactory)
    {
        $this->pdo = $pdo;
        $this->mapFactory = $mapFactory;
    }

    /**
     * @inheritDoc
     */
    public function query(string $query): QueryInterface
    {
        $pdo = $this->pdo;
        $statement = $pdo->prepare($query);

        if (!$statement) {
            $error = $pdo->errorInfo();
            throw new RuntimeException(sprintf('Could not create statement: %1$s', $error[2]));
        }

        $query = new PdoQuery($statement, $this->mapFactory);

        return $query;
    }
}
