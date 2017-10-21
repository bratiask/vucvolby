<?php

namespace bratiask\VucVolby\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use League\Csv\Reader;

class ImportVuc2017Command extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('import-vuc-2017');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getConnection()->prepare("TRUNCATE TABLE lsns_candidates_2017_vuc")->execute();

        $this->processCsv('vuc-2017-lsns-kandidati.csv', function($vuc_region_id, $vuc_subregion_id, $nr_of_candidates) {
            $this->insertRecord($vuc_region_id, $vuc_subregion_id, $nr_of_candidates);
        });
    }

    private function insertRecord($vuc_region_id, $vuc_subregion_id, $nr_of_candidates)
    {
        $statement = $this->getConnection()->prepare("
            INSERT INTO 
                lsns_candidates_2017_vuc
            VALUES(
                NULL,
                :vuc_region_id,
                :vuc_subregion_id,
                :nr_of_candidates
            )
        ");
        $statement->bindValue(':vuc_region_id', $vuc_region_id);
        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->bindValue(':nr_of_candidates', $nr_of_candidates);
        $statement->execute();
    }

    private function processCsv($filename, $callback)
    {
        $vuc_region_id = null;
        $vuc_subregion_id = null;

        $csv = Reader::createFromPath($filename, 'r');

        foreach ($csv->fetchAll() as $row)
        {
            $first_column = trim($row[0]);
            $second_column = trim($row[1]);

            if (!empty($first_column))
            {
                $vuc_region_id = $this->findRegionIdByName($first_column);
            }

            $vuc_subregion_id = $this->findSubregionIdByName($second_column, $vuc_region_id);

            $callback($vuc_region_id, $vuc_subregion_id, trim(str_replace(',', '', $row[2])));
        }
    }
}