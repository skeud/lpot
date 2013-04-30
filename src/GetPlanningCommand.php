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
        $planningConfig = $yaml->parse(file_get_contents(__DIR__ . '/config.yml'));
        $planningConfig['nbOfWeeks'] = $input->getOption('nbOfWeeks');

        $planningService = new PlanningService($planningConfig);
        $planning = $planningService->getTeamAvailabilities();

        // render planning
        $totalMdsAvailable = 0;

        foreach($planning as $date => $teamAvailability) {
            if (date('N', strtotime($date)) === '1') {
                $output->writeln('');
                $output->writeln('WK ' . date('W', strtotime($date)));
            }

            $output->writeln($date . ': <info>' . array_sum($teamAvailability['teamMembers']) . ' MD</info> ' . $teamAvailability['dateComments']);
            $totalMdsAvailable += array_sum($teamAvailability['teamMembers']);
        }

        $output->writeln('');
        $output->writeln('----------------');
        $output->writeln('Total:     <info>' . $totalMdsAvailable . ' MD</info>');
    }
}