<?php

use Doctrine\DBAL\Statement;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();

$container = new ContainerBuilder();
$loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/..'));
$loader->load('Resources/config/services.xml');

$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../Resources/views',
));

function getTable()
{

}

$app->after(function (Request $request, Response $response) {
    $response->headers->set(
        'Content-Type',
        $response->headers->get('Content-Type') . '; charset=UTF-8'
    );

    return $response;
});

$app->get('/', function () use ($container, $app) {
    /** @var Statement $statement */
    $statement = $container->get('connection')->prepare('   
        SELECT
            r.name AS region_name,
            sr.name AS subregion_name,
            MAX(v.nr_of_votes) AS max_votes,
            MIN(v.nr_of_votes) AS min_votes,
            l.lsns_nr_of_valid AS lsns_votes,
            lc.nr_of_candidates AS lsns_candidates,
            SUM(IF(v.nr_of_votes < l.lsns_nr_of_valid, 1, 0)) AS lsns_prediction,
            COUNT(*) AS count,
            l.municipality_names
        FROM
            aggr_lsns_votes_2016_parliament_by_subregion as l
        LEFT JOIN
            votes_2013_vuc AS v
        ON
            l.vuc_subregion_id = v.vuc_subregion_id
        LEFT JOIN
            lsns_candidates_2017_vuc AS lc
        ON
            l.vuc_subregion_id = lc.vuc_subregion_id
        INNER JOIN
            vuc_subregions AS sr
        ON
            v.vuc_subregion_id = sr.vuc_subregion_id
        INNER JOIN
            vuc_regions AS r
        ON
            sr.vuc_region_id = r.vuc_region_id
        WHERE
            v.elected = 1
        GROUP BY
            v.vuc_subregion_id
        ORDER BY
            sr.vuc_region_id, SUBSTRING(sr.name, 17) * 1 ');

    $statement->execute();

    return $app['twig']->render('index.html.twig', array(
        'table' => $statement->fetchAll(),
    ));
});

$app->run();