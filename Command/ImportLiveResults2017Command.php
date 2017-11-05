<?php

namespace bratiask\VucVolby\Command;

use Nette\Utils\Strings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Twig_Environment;

class ImportLiveResults2017Command extends ContainerAwareCommand
{
    /**
     * @var Twig_Environment
     */
    private $twig;

    protected function configure()
    {
        $this->setName('import-live-results');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $time = round(microtime(true));

//        $statement = $this->getConnection()->prepare("SELECT
//	r.name AS region_name,
//	sr.name AS subregion_name,
//	m.nr_of_representatives,
//	m.su_subregion_id
//FROM
//	votes_2017_vuc_meta AS m
//INNER JOIN
//	vuc_subregions_2017 AS sr
//ON
//	m.vuc_subregion_id = sr.vuc_subregion_id
//INNER JOIN
//	vuc_regions AS r
//ON
//	sr.vuc_region_id = r.vuc_region_id
//ORDER BY
//	r.su_region_id");
//
//
//        $statement->execute();

        $base_dir = __DIR__ . '/../web/vysledky/';

        $fs = new Filesystem();
//        $fs->remove(glob($base_dir . 'obvody.json'));
        //$fs->dumpFile($base_dir . 'obvody.json', json_encode($statement->fetchAll()));

        $subregions = json_decode(file_get_contents($base_dir . 'obvody.json'), true);
        $regions = array();

        foreach ($subregions as $subregion)
        {
            if (!isset($regions[$subregion['region_name']]))
            {
                $regions[$subregion['region_name']] = array(
                    'name' => $subregion['region_name'],
                    'subregions' => array(),
                );
            }

            $raw_content = file_get_contents('https://www.volbysr.sk/json/tab09c/' . $subregion['su_subregion_id'] . '.json?_=' . $time);

            $people = array();

            foreach (json_decode($raw_content, true) as $record)
            {
                $people[] = array(
                    'name' => $record['C02'] . ' ' . $record['C03'],
                    'party' => $record['C04'],
                    'nr_of_votes' => (int) $this->n($record['C05']),
                    'nr_of_votes_relative' => str_replace(',', '.', $record['C06']),
                );
            }

            uasort($people, function($person1, $person2) {
                return $person1['nr_of_votes'] > $person2['nr_of_votes'] ? -1 : 1;
            });

            $people = array_slice($people, 0, $subregion['nr_of_representatives']);

            $regions[$subregion['region_name']]['subregions'][$subregion['subregion_name']] = $people;
        }

        $fs->remove(glob($base_dir . 'data.json'));
        $fs->dumpFile($base_dir . 'data.json', json_encode($regions));

        $this->setTwigEnvironment($this->container->get('twig'));

        $fs = new Filesystem();
        $fs->remove(glob($base_dir . 'index.html'));

        $fs->dumpFile($base_dir . 'index.html', $this->getHTML());
    }

    public function setTwigEnvironment(Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    public function getHTML()
    {
        $base_dir = __DIR__ . '/../web/vysledky/';
        $regions = json_decode(file_get_contents($base_dir . 'data.json'), true);
        return $this->twig->render('results.html.twig', array('regions' => $regions));
    }
}