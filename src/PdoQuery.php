<?php
declare(strict_types=1);

namespace Dhii\PdoQuery;

use Dhii\Collection\CountableListInterface;
use Dhii\Collection\MapFactoryInterface;
use Dhii\Query\QueryInterface;
use PDO;
use PDOStatement;
use RuntimeException;
use Stringable;
use UnexpectedValueException;

class PdoQuery implements QueryInterface
{
    /** @var PDOStatement */
    protected $statement;
    /** @var array */
    protected $params = [];
    /** @var MapFactoryInterface */
    protected $mapFactory;

    public function __construct(PDOStatement $statement, MapFactoryInterface $mapFactory)
    {
        $this->statement = $statement;
        $this->mapFactory = $mapFactory;
    }

    /**
     * @inheritDoc
     */
    public function getResults(): iterable
    {
        $statement = $this->statement;
        $statement = $this->bindParams($statement, $this->params);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            $message = $this->getErrorMessage($statement);
            $debug = $this->getDebugInfo($statement);
            throw new RuntimeException($this->__('%1$s' . "\n" . 'Debug info:' . "\n" . '%2$s', [$message, $debug]));
        }

        $rows = $this->createResultSet($rows);

        return $rows;
    }

    /**
     * @inheritDoc
     */
    public function withParam(string $name, $value): QueryInterface
    {
        $query = $this->copy($this);
        $query->params[$name] = $value;

        return $query;
    }

    /**
     * @inheritDoc
     */
    public function withParams(array $params = []): QueryInterface
    {
        $query = $this->copy($this);
        $query->params = $params;

        return $query;
    }

    /**
     * @inheritDoc
     */
    public function withoutParams(array $params): QueryInterface
    {
        $query = $this->copy($this);

        foreach ($params as $name) {
            if (array_key_exists($name, $query->params)) {
                unset($query->params[$name]);
            }
        }

        return $query;
    }

    /**
     * Clones an instance of this class.
     *
     * @param PdoQuery $query The instance to clone.
     * @return PdoQuery The new instance.
     */
    protected function copy(PdoQuery $query): PdoQuery
    {
        return clone $query;
    }

    /**
     * Binds parameters to the statement.
     *
     * Will attempt to correctly set the type for binding based on param value type.
     *
     * @psalm-pure
     * @param PDOStatement $statement The statement to bind params to.
     * @param array $params The map of params to their values.
     * @return PDOStatement The statement with params bound.
     */
    protected function bindParams(PDOStatement $statement, array $params): PDOStatement
    {
        foreach ($params as $name => $value) {
            if ($value instanceof Stringable) {
                $value = (string) $value;
            }

            $bindType = PDO::PARAM_STR;
            switch (gettype($value)) {
                case 'boolean':
                case 'bool':
                    $bindType = PDO::PARAM_BOOL;
                    break;

                case 'integer':
                case 'int':
                    $bindType = PDO::PARAM_INT;
                    break;

                case 'float':
                case 'double':
                    $bindType = PDO::PARAM_STR;
                    $value = strval($value);
                    break;

                case 'null':
                case 'NULL':
                    $bindType = PDO::PARAM_NULL;
                    break;

                default:
                    $bindType = PDO::PARAM_STR;
                    break;
            }

            $statement->bindValue($name, $value, $bindType);
        }

        return $statement;
    }

    /**
     * Creates a new result set from a list of rows.
     *
     * @param list<array> $rows The list of rows.
     * @return CountableListInterface The result set.
     */
    protected function createResultSet(array $rows): CountableListInterface
    {
        return new ResultSet($rows, $this->mapFactory);
    }

    /**
     * Retrieves debug info of a statement.
     *
     * @param PDOStatement $statement The statement to get the debug info of.
     * @return string The debug info.
     * @throws RuntimeException If problem retrieving.
     */
    protected function getDebugInfo(PDOStatement $statement): string
    {
        ob_start();
        $statement->debugDumpParams();
        $info = ob_get_clean();

        if ($info === false) {
            throw new UnexpectedValueException('Could not retrieve output buffer contents');
        }

        return $info;
    }

    /**
     * Retrieves the error message of a statement, if present.
     *
     * @param PDOStatement $statement The statement to retrieve the error message of.
     * @return string|null The message, or null if none.
     */
    protected function getErrorMessage(PDOStatement $statement): ?string
    {
        if (!$statement->errorCode()) {
            return null;
        }

        $info = $statement->errorInfo();
        $message = isset($info[2]) ? $info[2] : null;

        return $message;
    }

    /**
     * Internationalizes string, interpolating params.
     *
     * @param string $string The string to internationalize.
     * @param array $params The parameters to interpolate.
     *
     * @return string The internationalized string with params interpolated.
     */
    protected function __(string $string, array $params = []): string
    {
        return vsprintf($string, $params);
    }
}
