<?php

namespace bratiask\VucVolby\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Container;

class ContainerAwareCommand extends Command
{
    /**
     * @var Container
     */
    protected $container;

    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    protected function findSubregionIdByName($name, $vuc_region_id)
    {
        $statement = $this->getConnection()->prepare("
            SELECT
                vuc_subregion_id
            FROM
                vuc_subregions 
            WHERE
                name = :name
                    AND
                vuc_region_id = :vuc_region_id");
        $statement->bindValue(':name', $name);
        $statement->bindValue(':vuc_region_id', $vuc_region_id);
        $statement->execute();
        return $statement->fetchColumn();
    }

    protected function findRegionIdByName($name)
    {
        $statement = $this->getConnection()->prepare("SELECT vuc_region_id FROM vuc_regions WHERE name = :name");
        $statement->bindValue(':name', $name);
        $statement->execute();
        return $statement->fetchColumn();
    }

    /**
     * @return Connection
     */
    protected function getConnection()
    {
        return $this->container->get('connection');
    }
}