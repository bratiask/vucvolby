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

    protected function n($value)
    {
        return trim(str_replace(array(',', ' '), array('', ''), $value));
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

    protected function findSubregion2017IdByName($name, $vuc_region_id)
    {
        $statement = $this->getConnection()->prepare("
            SELECT
                vuc_subregion_id
            FROM
                vuc_subregions_2017 
            WHERE
                name = :name
                    AND
                vuc_region_id = :vuc_region_id");
        $statement->bindValue(':name', $name);
        $statement->bindValue(':vuc_region_id', $vuc_region_id);
        $statement->execute();
        return $statement->fetchColumn();
    }

    protected function findMunicipalityIdByNameAndSubregionId($name, $vuc_subregion_id)
    {
        $statement = $this->getConnection()->prepare("
            SELECT
                municipality_id
            FROM
                municipalities 
            WHERE
                name = :name
                    AND
                vuc_subregion_id = :vuc_subregion_id");
        $statement->bindValue(':name', $name);
        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->execute();
        return $statement->fetchColumn();
    }

    protected function findMunicipality2017IdByNameAndSubregion2017Id($name, $vuc_subregion_id)
    {
        $statement = $this->getConnection()->prepare("
            SELECT
                municipality_id
            FROM
                municipalities_2017 
            WHERE
                name = :name
                    AND
                vuc_subregion_id = :vuc_subregion_id");
        $statement->bindValue(':name', $name);
        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->execute();
        return $statement->fetchColumn();
    }

    protected function findMunicipalityAndRegionIdIdByMunicipalityName($name)
    {
        $statement = $this->getConnection()->prepare("
            SELECT
                m.municipality_id,
                sr.vuc_region_id
            FROM
                municipalities AS m
            INNER JOIN
                vuc_subregions AS sr
            ON
                m.vuc_subregion_id = sr.vuc_subregion_id
            WHERE
                m.name = :name");
        $statement->bindValue(':name', $name);
        $statement->execute();
        $result = $statement->fetchAll();

        return count($result) === 1 ? array($result[0]['municipality_id'], $result[0]['vuc_region_id']) : array(null, null);

    }

    protected function findMunicipalityAndSubregionIdIdByMunicipalityNameAndRegionId($municipality_name, $vuc_region_id)
    {
        $statement = $this->getConnection()->prepare("
            SELECT
                m.municipality_id,
                m.vuc_subregion_id
            FROM
                municipalities As m
            INNER JOIN
                vuc_subregions AS sr
            ON
                m.vuc_subregion_id = sr.vuc_subregion_id
            WHERE
                m.name = :name
                    AND
                sr.vuc_region_id = :vuc_region_id");
        $statement->bindValue(':name', $municipality_name);
        $statement->bindValue(':vuc_region_id', $vuc_region_id);
        $statement->execute();
        $result = $statement->fetchAll();

        return count($result) === 1 ? array($result[0]['municipality_id'], $result[0]['vuc_subregion_id']) : array(null, null);
    }

    protected function findMunicipality2017AndSubregion2017IdIdByMunicipality2017NameAndRegionId($municipality_name, $vuc_region_id)
    {
        $statement = $this->getConnection()->prepare("
            SELECT
                m.municipality_id,
                m.vuc_subregion_id
            FROM
                municipalities_2017 As m
            INNER JOIN
                vuc_subregions_2017 AS sr
            ON
                m.vuc_subregion_id = sr.vuc_subregion_id
            WHERE
                m.name = :name
                    AND
                sr.vuc_region_id = :vuc_region_id");
        $statement->bindValue(':name', $municipality_name);
        $statement->bindValue(':vuc_region_id', $vuc_region_id);
        $statement->execute();
        $result = $statement->fetchAll();

        return count($result) === 1 ? array($result[0]['municipality_id'], $result[0]['vuc_subregion_id']) : array(null, null);
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