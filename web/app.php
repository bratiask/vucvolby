<?php

use bratiask\VucVolby\Command\GenerateIndexContentCommand;
use bratiask\VucVolby\Command\GenerateMunicipalityContentCommand;
use Doctrine\DBAL\Statement;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
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

$app->get('/koho-volit', function () use ($container, $app) {
    /** @var GenerateIndexContentCommand $command */
    $command = $container->get('command.GenerateCandidatesContent');
    $command->setContainer($container);
    $command->setTwigEnvironment($app['twig']);

    return $command->getHtml();
});
$app->get('/v', function () use ($container, $app) {
    /** @var GenerateIndexContentCommand $command */
    $command = $container->get('command.ImportLiveResults2017');
    $command->setContainer($container);
    $command->setTwigEnvironment($app['twig']);

    return $command->getHtml();
});

$app->get('/', function () use ($container, $app) {
    /** @var GenerateIndexContentCommand $command */
    $command = $container->get('command.GenerateIndexContent');
    $command->setContainer($container);
    $command->setTwigEnvironment($app['twig']);

    return $command->getHtml();
});

$app->get('/m/{municipality_id}.html', function ($municipality_id) use ($container, $app) {
    /** @var GenerateMunicipalityContentCommand $command */
    $command = $container->get('command.GenerateMunicipalityContent');
    $command->setContainer($container);
    $command->setTwigEnvironment($app['twig']);

    return $command->getHtml($municipality_id);
});

$app->get('/list/m/{municipality_id}.html', function ($municipality_id) use ($container, $app) {
    /** @var GenerateMunicipalityContentCommand $command */
    $command = $container->get('command.GenerateMunicipalityCandidatesContent');
    $command->setContainer($container);
    $command->setTwigEnvironment($app['twig']);

    return $command->getHtml($municipality_id);
});

$app->get('/municipalities.json', function () use ($container, $app) {
    return getMunicipalities($container, function($municipality) {
        return '/m/' . $municipality['id'] . '.html';
    });
});

$app->get('/list/municipalities.json', function () use ($container, $app) {
    return getMunicipalities($container, function($municipality) {
        return '/list/m/' . $municipality['id'] . '.html';
    });
});

function getMunicipalities($container, $url_callback)
{
    /** @var Statement $statement */
    $statement = $container->get('connection')->prepare('   
        SELECT
            municipality_id AS id,
            name,
            IF(duplicate_info IS NULL, name, CONCAT(name, \' (okres \', duplicate_info,\')\')) AS unique_name
        FROM
            municipalities
        ORDER BY
            name');

    $statement->execute();

    return new JsonResponse(['items' => [['id' => '', 's' => '', 'text' => '']] + array_map(function($municipality) use ($url_callback) {
            return [
                'id' => $url_callback($municipality),
                's' => str_replace('-', ' ', \Nette\Utils\Strings::webalize($municipality['name'])),
                'text' => $municipality['unique_name']
            ];
        }, $statement->fetchAll())]);
}

$app->get('/data', function () use ($container, $app) {
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

    return $app['twig']->render('data.html.twig', array(
        'table' => $statement->fetchAll(),
    ));
});

$app->run();