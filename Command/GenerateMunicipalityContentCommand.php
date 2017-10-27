<?php

namespace bratiask\VucVolby\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig_Environment;

class GenerateMunicipalityContentCommand extends ContainerAwareCommand
{
    /**
     * @var Twig_Environment
     */
    private $twig;

    protected function configure()
    {
        $this->setName('generate-municipality-content');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setTwigEnvironment($this->container->get('twig'));

//        $this->getConnection()->prepare("TRUNCATE TABLE votes_2012_parliament")->execute();
//
//        $this->processCsv('nrsr_2012.csv', function($municipality_id, $row) {
//            $this->insertRecord($municipality_id, $this->n($row[2]), $this->n($row[12]), $this->n($row[6]), $this->n($row[13]), $this->n($row[16]));
//        });
    }

    public function setTwigEnvironment(Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    public function getHtml($municipality_id)
    {
        return $this->twig->render('includes/municipality.html.twig', array(
            'municipality_id' => $municipality_id
        ));
    }
}