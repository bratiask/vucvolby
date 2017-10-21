#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';
//require __DIR__.'/commands/EntityCommand.php';

use bratiask\VucVolby\Command\ContainerAwareCommand;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

$container = new ContainerBuilder();
$loader = new XmlFileLoader($container, new FileLocator(__DIR__));
$loader->load('Resources/config/services.xml');

use Symfony\Component\Console\Application;

$application = new Application();

foreach ($container->findTaggedServiceIds('command') as $command_service_id => $dummy)
{
    /** @var ContainerAwareCommand $command */
    $command = $container->get($command_service_id);
    $command->setContainer($container);
    $application->add($command);
}

$application->run();