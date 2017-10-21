<?php

namespace bratiask\VucVolby\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use League\Csv\Reader;

class ImportVuc2013Command extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('import-vuc-2013');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getConnection()->prepare("TRUNCATE TABLE votes_2013_vuc")->execute();

        $this->processCsv('vuc-2013-kandidati.csv', function($vuc_region_id, $vuc_subregion_id, $row) {
            $this->insertPerson($vuc_region_id, $vuc_subregion_id, trim($row[0]), trim($row[1]), trim(str_replace(',', '', $row[2])));
        });

        $this->processCsv('vuc-2013-poslanci.csv', function($vuc_region_id, $vuc_subregion_id, $row) {
            $this->updateElectedStatus($vuc_region_id, $vuc_subregion_id, trim($row[1]), trim(str_replace(',', '', $row[2])));
        });
    }

    private function insertPerson($vuc_region_id, $vuc_subregion_id, $name, $party, $nr_of_votes)
    {
        $statement = $this->getConnection()->prepare("
            INSERT INTO 
                votes_2013_vuc
            VALUES(
                NULL,
                :vuc_region_id,
                :vuc_subregion_id,
                :name,
                :party,
                :nr_of_votes,
                :elected
            )
        ");
        $statement->bindValue(':vuc_region_id', $vuc_region_id);
        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->bindValue(':name', $name);
        $statement->bindValue(':party', $party);
        $statement->bindValue(':nr_of_votes', $nr_of_votes);
        $statement->bindValue(':elected', false);
        $statement->execute();
    }

    private function updateElectedStatus($vuc_region_id, $vuc_subregion_id, $name, $nr_of_votes)
    {
        $statement = $this->getConnection()->prepare("
            UPDATE 
                votes_2013_vuc
            SET
                elected = :elected
            WHERE
                vuc_region_id = :vuc_region_id
                    AND
                vuc_subregion_id = :vuc_subregion_id
                    AND
                nr_of_votes = :nr_of_votes
                    AND
                name = :name");
        $statement->bindValue(':vuc_region_id', $vuc_region_id);
        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->bindValue(':name', $name);
        $statement->bindValue(':nr_of_votes', $nr_of_votes);
        $statement->bindValue(':elected', true);
        $statement->execute();

        if ($statement->rowCount() !== 1)
        {
            echo "Not found: $name";
        }
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

            if (empty($first_column))
            {
                continue;
            }

            if (empty($second_column))
            {
                if (mb_strpos($first_column, 'Volebný obvod č.') === 0)
                {
                    $vuc_subregion_id = $this->findSubregionIdByName($first_column, $vuc_region_id);
                }
                else
                {
                    $vuc_region_id = $this->findRegionIdByName($first_column);
                }
            }
            else
            {
                $callback($vuc_region_id, $vuc_subregion_id, $row);
            }
        }
    }
}