<?php

namespace bratiask\VucVolby\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use League\Csv\Reader;

class ImportVuc2009Command extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('import-vuc-2009');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getConnection()->prepare("TRUNCATE TABLE votes_2009_vuc")->execute();

        $store = new \stdClass();
        $store->last_party = '';

        $this->processCsv('vuc-2009-poslanci.csv', function($vuc_region_id, $vuc_subregion_name, $row) use ($store) {
            $party = trim($row[0]);
            $party = empty($party) ? $store->last_party : $party;
            $this->insertPerson($vuc_region_id, $vuc_subregion_name, trim($row[1]), $party, trim(str_replace(' ', '', $row[2])), true);
            $store->last_party = $party;
        });

        $store->last_party = '';

        $this->processCsv('vuc-2009-neuspesni-kandidati.csv', function($vuc_region_id, $vuc_subregion_name, $row) use ($store) {
            $party = trim($row[1]);
            $party = empty($party) ? $store->last_party : $party;
            $this->insertPerson($vuc_region_id, $vuc_subregion_name, trim($row[0]), $party, trim(str_replace(' ', '', $row[2])), false);
            $store->last_party = $party;
        });
    }

    private function insertPerson($vuc_region_id, $vuc_subregion_name, $name, $party, $nr_of_votes, $elected)
    {
        $statement = $this->getConnection()->prepare("
            INSERT INTO 
                votes_2009_vuc
            (
                id,
                vuc_region_id,
                vuc_subregion_name,
                name,
                party,
                nr_of_votes,
                elected
            )
            VALUES(
                NULL,
                :vuc_region_id,
                :vuc_subregion_name,
                :name,
                :party,
                :nr_of_votes,
                :elected
            )
        ");
        $statement->bindValue(':vuc_region_id', $vuc_region_id);
        $statement->bindValue(':vuc_subregion_name', $vuc_subregion_name);
        $statement->bindValue(':name', $name);
        $statement->bindValue(':party', $party);
        $statement->bindValue(':nr_of_votes', $nr_of_votes);
        $statement->bindValue(':elected', $elected);
        $statement->execute();
    }

    private function processCsv($filename, $callback)
    {
        $vuc_region_id = null;
        $vuc_subregion_name = '';

        $csv = Reader::createFromPath($filename, 'r');

        foreach ($csv->fetchAll() as $row)
        {
            $first_column = trim($row[0]);
            $second_column = trim($row[1]);

            if (empty($first_column) && empty($second_column))
            {
                continue;
            }

            if (empty($second_column))
            {
                if (mb_strpos(mb_strtolower($first_column), 'kraj') > 0)
                {
                    $vuc_region_id = $this->findRegionIdByName($first_column);
                }

                if (null !== $vuc_region_id)
                {
                    $vuc_subregion_name = $first_column;
                }
            }
            else
            {
                $callback($vuc_region_id, $vuc_subregion_name, $row);
            }
        }
    }
}