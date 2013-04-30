<?php

namespace src;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;

class GetPlanningCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('planning:get')
            ->setDescription('Get planning')
            ->addOption(
                'outputType',
                null,
                InputOption::VALUE_OPTIONAL,
                'Set this option to define whether you want to output the planning in the console or in an HTML file (\'console\' by default)',
                'console'
            )
            ->addOption(
                'nbOfWeeks',
                null,
                InputOption::VALUE_OPTIONAL,
                'Set this option to define the number of weeks of planning you want to get (2 by default)',
                2
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Building planning...');

        $yaml = new Parser();
        $config = $yaml->parse(file_get_contents(__DIR__ . '/config.yml'));

        $planning = new Planning(
            $config['teamMembers'],
            $config['billablePercentage'],
            $config['teamOffice'],
            $config['personalDayOffString'],
            $config['liipDayOffString'],
            $config['innoDayOffString'],
            $input->getOption('nbOfWeeks'),
            $config['daysToNotTaKeIntoAccount'],
            $config['teamCalId'],
            $config['liipInternEventsCalId'],
            $config['calClientId'],
            $config['calClientSecret'],
            $config['calRedirectUri'],
            $config['calToken']
        );
        $planning->buildPlanning();
        $planning->render($output, $input->getOption('outputType'));
    }
}