<?php

namespace bratiask\VucVolby\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Config\Definition\Exception\Exception;
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
        $this->getConnection()->prepare("TRUNCATE TABLE votes_2017_vuc")->execute();

        $this->processCsv('vuc-2017-kandidati.csv', function($vuc_region_id, $vuc_subregion_id, $municipality_id, $row) {
            $this->insertPerson($vuc_region_id, $vuc_subregion_id, $municipality_id, trim($row[3]) . ' ' . trim($row[4]), trim($row[8]), trim($row[1]),
                trim($row[2]), trim($row[6]), trim($row[5]));
        });
    }

    private function insertPerson($vuc_region_id, $vuc_subregion_id, $municipality_id, $name, $party, $subregion_name_2017, $number, $occupation, $age)
    {
        $statement = $this->getConnection()->prepare("
            INSERT INTO 
                votes_2017_vuc
            VALUES(
                NULL,
                :vuc_region_id,
                :vuc_subregion_id,
                :municipality_id,
                :name,
                :party,
                :nr_of_votes,
                :elected,
                :subregion_name_2017,
                :number,
                :occupation,
                :age
            )
        ");
        $statement->bindValue(':vuc_region_id', $vuc_region_id);
        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->bindValue(':municipality_id', $municipality_id);
        $statement->bindValue(':name', $name);
        $statement->bindValue(':party', $party);
        $statement->bindValue(':nr_of_votes', 0);
        $statement->bindValue(':elected', false);
        $statement->bindValue(':subregion_name_2017', $subregion_name_2017);
        $statement->bindValue(':number', $number);
        $statement->bindValue(':occupation', $occupation);
        $statement->bindValue(':age', $age);
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
            $municipality_column = trim($row[7]);

            if (empty($first_column) || empty($second_column) || empty($municipality_column))
            {
                continue;
            }

            $vuc_region_id = $this->findRegionIdByName($first_column);
            $vuc_subregion_id = $this->findSubregion2017IdByName($second_column, $vuc_region_id);

            if (!empty($vuc_subregion_id))
            {
                $municipality_id = $this->findMunicipality2017IdByNameAndSubregion2017Id($municipality_column, $vuc_subregion_id);
            }

            if (empty($municipality_id))
            {
                list($municipality_id, $vuc_subregion_id) = $this->findMunicipality2017AndSubregion2017IdIdByMunicipality2017NameAndRegionId($municipality_column, $vuc_region_id);

                if (empty($vuc_subregion_id))
                {
                    throw new Exception('Subregion wasn\'t found: ' . $first_column . ' - '  . $second_column);
                }

                if (empty($municipality_id))
                {
                    throw new Exception('Municipality wasn\'t found: ' . $first_column . ' - ' . $second_column . ' - ' . $municipality_column);
                }
            }

            $callback($vuc_region_id, $vuc_subregion_id, $municipality_id, $row);
        }
    }
}