<?php

namespace bratiask\VucVolby\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use League\Csv\Reader;

class ImportNrsr2012Command extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('import-nrsr-2012');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getConnection()->prepare("TRUNCATE TABLE votes_2012_parliament")->execute();

        $this->processCsv('nrsr_2012.csv', function($municipality_id, $row) {
            $this->insertRecord($municipality_id, $this->n($row[2]), $this->n($row[12]), $this->n($row[6]), $this->n($row[13]), $this->n($row[16]));
        });
    }

    private function insertRecord($municipality_id, $total_nr_of_valid, $lsns_nr_of_valid, $sns_nr_of_valid, $smer_nr_of_valid, $kss_nr_of_valid)
    {
        $statement = $this->getConnection()->prepare("
            INSERT INTO 
                votes_2012_parliament
            VALUES(
                :municipality_id,
                :total_nr_of_valid,
                :lsns_nr_of_valid,
                :sns_nr_of_valid,
                :smer_nr_of_valid,
                :kss_nr_of_valid
            )
        ");
        $statement->bindValue(':municipality_id', $municipality_id);
        $statement->bindValue(':total_nr_of_valid', $total_nr_of_valid);
        $statement->bindValue(':lsns_nr_of_valid', $lsns_nr_of_valid);
        $statement->bindValue(':sns_nr_of_valid', $sns_nr_of_valid);
        $statement->bindValue(':smer_nr_of_valid', $smer_nr_of_valid);
        $statement->bindValue(':kss_nr_of_valid', $kss_nr_of_valid);
        $statement->execute();
    }

    private function processCsv($filename, $callback)
    {
        $vuc_region_id = null;
        $municipality_id = null;
        $last_municipality_name = '';

        $csv = Reader::createFromPath($filename, 'r');

        foreach ($csv->fetchAll() as $row)
        {
            $first_column = trim($row[0]);

            if (empty($first_column) || mb_strpos($first_column, 'Obvod') === 0)
            {
                continue;
            }

            $municipality_id = trim($row[1]);

            if (empty($municipality_id))
            {
                $last_vuc_region_id = $vuc_region_id;
                list($municipality_id, $vuc_region_id) = $this->findMunicipalityAndRegionIdIdByMunicipalityName($first_column);

                if (empty($municipality_id))
                {
                    if (empty($last_vuc_region_id))
                    {
                        throw new Exception('Municipality wasn\'t found or multiple municipalities were found: ' . $first_column . ' (after ' . $last_municipality_name . ')');
                    }
                    else
                    {
                        list($municipality_id, $dummy) = $this->findMunicipalityAndSubregionIdIdByMunicipalityNameAndRegionId($first_column, $last_vuc_region_id);
                    }

                    if (empty($municipality_id))
                    {
                        throw new Exception('Municipality wasn\'t found: ' . $first_column . ' (after ' . $last_municipality_name . ')');
                    }
                    else
                    {
                        $vuc_region_id = $last_vuc_region_id;
                    }
                }
            }

            $last_municipality_name = $first_column;

            $callback($this->findSuMunicipalityIdByMunicipalityId($municipality_id), $row);
        }
    }

    private function findSuMunicipalityIdByMunicipalityId($municipality_id)
    {
        $statement = $this->getConnection()->prepare("
            SELECT
                su_municipality_id
            FROM
                municipalities 
            WHERE
                municipality_id = :municipality_id");
        $statement->bindValue(':municipality_id', $municipality_id);
        $statement->execute();
        return $statement->fetchColumn();
    }
}