<?php

namespace bratiask\VucVolby\Command;

use Nette\Utils\Strings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Twig_Environment;

class GenerateIndexContentCommand extends ContainerAwareCommand
{
    /**
     * @var Twig_Environment
     */
    private $twig;

    protected function configure()
    {
        $this->setName('generate-index-content');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setTwigEnvironment($this->container->get('twig'));

        $statement = $this->getConnection()->prepare("SELECT municipality_id FROM municipalities");
        $statement->execute();

        $base_dir = __DIR__ . '/../web/';

        $fs = new Filesystem();
        $fs->remove(glob($base_dir . 'index.html'));

        $fs->dumpFile($base_dir . 'index.html', $this->getHtml());
    }

    public function setTwigEnvironment(Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    public function getHtml()
    {
        return $this->twig->render('index.html.twig', array());
    }
}