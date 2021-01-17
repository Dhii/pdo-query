<?php

namespace Dhii\PdoQuery\Test\Func;

use Dhii\Collection\MapFactoryInterface;
use Dhii\Container\DataStructureBasedFactory;
use Dhii\Container\DictionaryFactory;
use Dhii\PdoQuery\PdoQueryFactory as Subject;
use Dhii\Query\QueryInterface;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PdoQueryFactoryTest extends TestCase
{
    /**
     * @param PDO $pdo
     * @param MapFactoryInterface $mapFactory
     * @return Subject&MockObject
     */
    protected function createSubject(PDO $pdo, MapFactoryInterface $mapFactory): Subject
    {
        $mock = $this->getMockBuilder(Subject::class)
            ->setMethods(null)
            ->setConstructorArgs([$pdo, $mapFactory])
            ->getMock();

        return $mock;
    }

    public function testQuery()
    {
        {
            $tableName = 'persons';
            $data = [
                [
                    'id' => null,
                    'name' => 'Anton',
                    'dob' => '1987-11-03',
                    'weight' => 71.3,
                    'is_dev' => true,
                    'teeth' => 31,

                ],
                [
                    'id' => null,
                    'name' => 'Melissa',
                    'dob' => '1988-12-30',
                    'weight' => 64,
                    'is_dev' => true,
                    'teeth' => 30,
                ],
                [
                    'id' => null,
                    'name' => 'Wendy',
                    'dob' => '2005-10-22',
                    'weight' => 60.8,
                    'is_dev' => true,
                    'teeth' => 32,
                ],
                [
                    'id' => null,
                    'name' => 'Kenneth',
                    'dob' => '2007-02-19',
                    'weight' => 82.1,
                    'is_dev' => true,
                    'teeth' => 32,
                ],
                [
                    'id' => null,
                    'name' => 'James',
                    'dob' => '1992-05-17',
                    'weight' => 74.5,
                    'is_dev' => false,
                    'teeth' => 32,
                ],
            ];
            $minDob = '2003-01-16';
            $maxWeight = 65;
            $isDev = true;
            $minTeeth = 31;
            $queryString = 'SELECT
                                `id`,
                                `name`,
                                `dob`,
                                `weight`,
                                `is_dev`,
                                `teeth`
                            FROM `persons`
                            WHERE (`dob` <= :maxDob OR `weight` <= :maxWeight)
                              AND `is_dev` = :isDev
                              AND `teeth` >= :minTeeth';
            $pdo = $this->createConnection($tableName, $data);
            $factory = $this->createMapFactory();
            $subject = $this->createSubject($pdo, $factory);
        }

        {
            $expectedRows = $this->sortRows([$data[0], $data[2]], 'name');
            $query = $subject->query($queryString)->withParams([
                'maxDob' => $minDob,
                'maxWeight' => $maxWeight,
                'isDev' => $isDev,
                'minTeeth' => $minTeeth,
           ]);
            $this->assertInstanceOf(QueryInterface::class, $query);

            $rowSet = $query->getResults();
            $this->assertIsIterable($rowSet);

            $rows = [];
            foreach ($rowSet as $row) {
                $rows[] = iterator_to_array($row);
            }
            $rows = $this->sortRows($rows, 'name');
            $this->assertEquals($expectedRows, $rows);
            $this->assertCount(count($expectedRows), $rowSet);
        }
    }

    protected function createMapFactory(): MapFactoryInterface
    {
        return new DataStructureBasedFactory(new DictionaryFactory());
    }

    protected function createConnection(string $tableName, array $data): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE `$tableName` (`id` int(6) PRIMARY KEY, `name` varchar(255), `dob` datetime, `weight` float(5), `is_dev` bool, `teeth` int(2))");

        foreach ($data as $row) {
            $valuesString = $this->prepareValuesString($row);
            $fieldsString = $this->prepareFieldsString($row);
            $query = "INSERT INTO `$tableName` ($fieldsString) VALUES ($valuesString)";
            $pdo->exec($query);
        }

        return $pdo;
    }

    protected function prepareValuesString(array $row): string
    {
        $valuesString = '';
        foreach ($row as $name => $value) {
            if (is_string($value)) {
                $value = "'$value'";
            }
            elseif ($value === null) {
                $value = 'NULL';
            }
            elseif (is_bool($value)) {
                $value = $value ? 'TRUE' : 'FALSE';
            }
            $valuesString .= "$value, ";
        }
        $valuesString = substr($valuesString, -2, 2) === ', '
            ? substr($valuesString, 0, -2)
            : $valuesString;

        return $valuesString;
    }

    protected function prepareFieldsString(array $row): string
    {
        return '`' . implode('`, `', array_keys($row)) . '`';
    }

    protected function sortRows(array $rows, string $field): array
    {
        usort($rows, function ($rowA, $rowB) use ($field) {
            return $rowB[$field] <=> $rowA[$field];
        });

        return $rows;
    }
}
