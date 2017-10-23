<?php

namespace bratiask\VucVolby\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use League\Csv\Reader;

class ImportVuc2013MetaCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('import-vuc-2013-meta');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getConnection()->prepare("TRUNCATE TABLE votes_2013_vuc_meta")->execute();

        $this->processCsv('vuc-2013-meta.csv', function($vuc_region_id, $vuc_subregion_id, $row) {
            $this->insertRow($vuc_region_id, $vuc_subregion_id,
                $this->n($row[2]), $this->n($row[3]), $this->n($row[5]), $this->n($row[7]), $this->n($row[8]), $this->n($row[9]));
        });
    }

    private function insertRow($vuc_region_id, $vuc_subregion_id, $nr_of_eligible, $nr_of_envelopes, $nr_of_voters,
                               $nr_of_valid_for_chairman, $nr_of_valid_for_representatives, $nr_of_representatives)
    {
        $statement = $this->getConnection()->prepare("
            INSERT INTO 
                `votes_2013_vuc_meta` 
            (
                `vuc_region_id`, 
                `vuc_subregion_id`, 
                `nr_of_eligible`, 
                `nr_of_envelopes`, 
                `nr_of_voters`, 
                `nr_of_valid_for_chairmain`, 
                `nr_of_valid_for_representatives`, 
                `nr_of_representatives`
            )
            VALUES 
            (
                :vuc_region_id,
                :vuc_subregion_id,
                :nr_of_eligible,
                :nr_of_envelopes,
                :nr_of_voters,
                :nr_of_valid_for_chairmain,
                :nr_of_valid_for_representatives,
                :nr_of_representatives
            )
        ");
        $statement->bindValue(':vuc_region_id', $vuc_region_id);
        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->bindValue(':nr_of_eligible', $nr_of_eligible);
        $statement->bindValue(':nr_of_envelopes', $nr_of_envelopes);
        $statement->bindValue(':nr_of_voters', $nr_of_voters);
        $statement->bindValue(':nr_of_valid_for_chairmain', $nr_of_valid_for_chairman);
        $statement->bindValue(':nr_of_valid_for_representatives', $nr_of_valid_for_representatives);
        $statement->bindValue(':nr_of_representatives', $nr_of_representatives);

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

            if (mb_strpos($first_column, 'Volebný obvod č.') === 0)
            {
                $vuc_subregion_id = $this->findSubregionIdByName($first_column, $vuc_region_id);

                if (empty($vuc_subregion_id))
                {
                    throw new Exception('Subregion wasn\'t found ' . $first_column);
                }

                $callback($vuc_region_id, $vuc_subregion_id, $row);
            }
            else
            {
                $vuc_region_id = $this->findRegionIdByName($first_column);
            }
        }
    }
}