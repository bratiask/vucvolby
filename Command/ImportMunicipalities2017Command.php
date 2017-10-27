<?php

namespace bratiask\VucVolby\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use League\Csv\Reader;

class ImportMunicipalities2017Command extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('import-municipalities-2017');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getConnection()->prepare("TRUNCATE TABLE municipalities_2017")->execute();

        $this->processCsv('vuc-2017-obce.csv', function($vuc_subregion_id, $row) {
            $this->insertRecord($vuc_subregion_id, trim($row[3]), trim($row[2]));
        });
    }

    private function insertRecord($vuc_subregion_id, $name, $su_municipality_id)
    {
        $statement = $this->getConnection()->prepare("
            INSERT INTO 
                municipalities_2017
            VALUES(
                NULL,
                :su_municipality_id,
                :vuc_subregion_id,
                :name,
                ''
            )
        ");
        $statement->bindValue(':su_municipality_id', $su_municipality_id);
        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->bindValue(':su_municipality_id', $su_municipality_id);
        $statement->bindValue(':name', $name);
        $statement->execute();
    }

    private function processCsv($filename, $callback)
    {
        $vuc_region_id = null;
        $vuc_subregion_id = null;
        $municipality_id = null;

        $csv = Reader::createFromPath($filename, 'r');

        foreach ($csv->fetchAll() as $row)
        {
            $first_column = trim($row[0]);
            $second_column = trim($row[1]);
            $third_column = trim($row[2]);

            if (empty($first_column) || empty($second_column) || empty($third_column))
            {
                continue;
            }

            $vuc_region_id = $this->findRegionIdByName($first_column);
            $vuc_subregion_id = $this->findSubregion2017IdByName($second_column, $vuc_region_id);

            if (empty($vuc_subregion_id))
            {
                $statement = $this->getConnection()->prepare("
                    INSERT INTO 
                        vuc_subregions_2017
                    VALUES(
                        NULL,
                        :vuc_region_id,
                        :name
                    )
                ");

                $statement->bindValue(':vuc_region_id', $vuc_region_id);
                $statement->bindValue(':name', $second_column);
                $statement->execute();

                $vuc_subregion_id = $this->getConnection()->lastInsertId();
            }

            $callback($vuc_subregion_id, $row);
        }
    }
}