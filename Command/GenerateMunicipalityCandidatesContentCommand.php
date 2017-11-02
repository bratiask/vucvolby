<?php

namespace bratiask\VucVolby\Command;

use Nette\Utils\Strings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Twig_Environment;

class GenerateMunicipalityCandidatesContentCommand extends ContainerAwareCommand
{
    /**
     * @var Twig_Environment
     */
    private $twig;

    protected function configure()
    {
        $this->setName('generate-municipality-candidates-content');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setTwigEnvironment($this->container->get('twig'));

        $statement = $this->getConnection()->prepare("SELECT municipality_id FROM municipalities");
        $statement->execute();

        $base_dir = __DIR__ . '/../web/list/m/';

        $fs = new Filesystem();
        $fs->remove(glob($base_dir . '*.*'));

        foreach ($statement->fetchAll() as $municipality)
        {
            $fs->dumpFile($base_dir . $municipality['municipality_id'] . '.html', $this->getHtml($municipality['municipality_id']));
        }
    }

    public function setTwigEnvironment(Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    public function getHtml($municipality_id)
    {
        $municipality = $this->getMunicipality($municipality_id);
        $representatives = $this->getNrOf2013VotesByVucSubregionId($municipality['vuc_subregion_id']);

        $votes = array_map(function($representative) {
            return $representative['nr_of_votes'];
        }, $representatives);

        if (count($votes) > 0)
        {
            $min_votes = $votes[0];
            $votes_info = count($votes) === 1 ? $votes[0] : ('od&nbsp;' . $votes[0] . '&nbsp;do&nbsp;' . $votes[count($votes) - 1]);
            $municipalities = $this->getMunicipalitiesByVucSubregionId($municipality['vuc_subregion_2017_id']);
            $nr_of_municipalities = count($municipalities);
            $subregion_info = $this->getSubregionInfo($municipalities, 60);
        }
        else
        {
            $min_votes = 0;
            $votes_info = '';
            $nr_of_municipalities = 0;
            $subregion_info = [
                'visible' => '',
                'hidden' => '',
            ];
        }

        return $this->twig->render('includes/municipality-candidates.html.twig', array(
            'nr_of_representatives' => $this->getNrOfRepresentativesByVucSubregionId($municipality['vuc_subregion_2017_id']),
            'municipality' => $municipality,
            'all_candidates' => $this->getAllCandidates($municipality),
            'nr_of_lsns_candidates' => $this->getNrOfLsnsCandidatesByVucSubregionId($municipality['vuc_subregion_2017_id']),
            'nr_of_municipalities' => $nr_of_municipalities,
            'subregion_info' => $subregion_info,
            'min_votes' => $min_votes,
            'votes' => $votes,
            'votes_info' => $votes_info,
            'nrsr_info' => $this->getLsns2016ResultsByVucSubregionId($municipality['vuc_subregion_id'])
        ));
    }

    private function getAllCandidates($municipality)
    {
        foreach ($this->get2017CandidatesByVucSubregionId($municipality['vuc_subregion_2017_id']) as $candidate)
        {
            yield $candidate + ['past_elections' => $this->getCandidatesByVucSubregionIdAndName($municipality['vuc_subregion_id'], $candidate['name'])];
        }
    }

    private function getSubregionInfo($municipalities, $max_visible_length)
    {
        $nr_of_municipalities = count($municipalities);
        $part = 'visible';
        $parts = [
            'visible' => array(),
            'hidden' => array()
        ];
        $length = 0;

        for ($f = 0; $f < $nr_of_municipalities; $f++)
        {
            $name = $municipalities[$f]['name'];
            $length += mb_strlen($name);

            $parts[$part][] = $name;

            if ($length > $max_visible_length)
            {
                $part = 'hidden';
            }
        }

        return [
            'visible' => empty($parts['hidden']) ? $this->joinWords($parts['visible'], ', ', ' a ') : join($parts['visible'], ', '),
            'hidden' => empty($parts['hidden']) ? '' : (count($parts['hidden']) === 1 ? (' a ' . $parts['hidden'][0]) : (', ' . $this->joinWords($parts['hidden'], ', ', ' a ')))
        ];
    }

    private function joinWords($words, $separator, $last_separator)
    {
        if (count($words) === 0)
        {
            return '';
        }

        if (count($words) === 1)
        {
            return $words[0];
        }

        $last_word = array_pop($words);

        return join($separator, $words) . $last_separator . $last_word;
    }

    private function getNrOfRepresentativesByVucSubregionId($vuc_subregion_id)
    {
        $statement = $this->getConnection()->prepare("
            SELECT
                nr_of_representatives
            FROM 
                votes_2017_vuc_meta
            WHERE
                vuc_subregion_id = :vuc_subregion_id");
        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->execute();
        return $statement->fetchColumn();
    }

    private function getMunicipality($municipality_id)
    {
        $statement = $this->getConnection()->prepare("SELECT m.*, m17.vuc_subregion_id AS vuc_subregion_2017_id FROM municipalities AS m INNER JOIN municipalities_2017 AS m17 ON m.su_municipality_id = m17.su_municipality_id WHERE m.municipality_id = :municipality_id");
        $statement->bindValue(':municipality_id', $municipality_id);
        $statement->execute();
        return $statement->fetch();
    }

    private function getNrOfLsnsCandidatesByVucSubregionId($vuc_subregion_id)
    {
        $statement = $this->getConnection()->prepare("SELECT
            COUNT(*) AS nr_of_candidates
            FROM
                votes_2017_vuc AS v
            WHERE
                v.vuc_subregion_id = :vuc_subregion_id AND (party = 'LS Pevnost Slovensko' OR party = 'LS Nase Slovensko')");

        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->execute();
        return $statement->fetchColumn();
    }

    private function getNrOf2013VotesByVucSubregionId($vuc_subregion_id)
    {
        $statement = $this->getConnection()->prepare("SELECT
                nr_of_votes
            FROM
                votes_2013_vuc AS v
            WHERE
                v.elected = 1 AND v.vuc_subregion_id = :vuc_subregion_id
            ORDER BY
                nr_of_votes");

        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->execute();
        return $statement->fetchAll();
    }

    private function get2017CandidatesByVucSubregionId($vuc_subregion_id)
    {
        $statement = $this->getConnection()->prepare("
            SELECT
                *
            FROM
                votes_2017_vuc AS v
            WHERE
                v.vuc_subregion_id = :vuc_subregion_id
                    AND 
                party != 'LS Pevnost Slovensko'
                    AND 
                party != 'LS Nase Slovensko'
                    AND 
                party != 'KSS'
                    AND 
                party != 'VZDOR'
                    AND 
                party != 'ÚSVIT'
                    AND 
                party != 'PSN'
                    AND 
                party != 'Slovenská ľudová strana (SĽS)'
                    AND 
                (is_lsns IS NULL OR is_lsns != 1)
            ORDER BY
                number");

        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->execute();
        return $statement->fetchAll();
    }

    private function getCandidatesByVucSubregionIdAndName($vuc_subregion_id, $name)
    {
        $result = array();

        foreach (array(2013, 2009, 2005) as $year)
        {
            $statement = $this->getConnection()->prepare("
            SELECT
                party,
                nr_of_votes,
                elected
            FROM
                votes_{$year}_vuc
            WHERE
                vuc_subregion_id = :vuc_subregion_id
                    AND 
                party != 'LS Pevnost Slovensko'
                    AND 
                party != 'LS Nase Slovensko'
                    AND
                name = :name");

            $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
            $statement->bindValue(':name', $name);
            $statement->execute();
            $record = $statement->fetch();

            if (false !== $record)
            {
                $result[$year] = $record;
            }
        }

        return $result;
    }

    private function getLsns2016ResultsByVucSubregionId($vuc_subregion_id)
    {
        $statement = $this->getConnection()->prepare("
            SELECT
                *
            FROM
                aggr_lsns_votes_2016_parliament_by_subregion
            WHERE
                vuc_subregion_id = :vuc_subregion_id");

        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->execute();
        return $statement->fetch();
    }

    private function getMunicipalitiesByVucSubregionId($vuc_subregion_id)
    {
        $statement = $this->getConnection()->prepare("
            SELECT
                name
            FROM
                municipalities_2017
            WHERE
                vuc_subregion_id = :vuc_subregion_id
            ORDER BY
                name");

        $statement->bindValue(':vuc_subregion_id', $vuc_subregion_id);
        $statement->execute();
        return $statement->fetchAll();
    }
}