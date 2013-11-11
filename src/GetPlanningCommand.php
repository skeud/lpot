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
            ->setName('planning:availability')
            ->setDescription('Generate availability planning')
            ->addOption(
                'startDate',
                null,
                InputOption::VALUE_OPTIONAL,
                'Set this option to define the start date of your generated planning (last monday - or current day if we are monday - by default)',
                date('N', strtotime('now')) == 1 ? date('Y-m-d', strtotime('now')) : date('Y-m-d', strtotime('last monday'))
            )
            ->addOption(
                'endDate',
                null,
                InputOption::VALUE_OPTIONAL,
                'Set this option to define the end date of your generated planning (next sunday - or current day if we are sunday - by default)',
                date('N', strtotime('now')) == 7 ? date('Y-m-d', strtotime('now')) : date('Y-m-d', strtotime('next sunday'))
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->isValidDateRange($input->getOption('startDate'), $input->getOption('endDate'))) {
            $output->writeln('<error>Invalid date range given (startDate = ' . $input->getOption('startDate') . ', endDate ' . $input->getOption('endDate') . ')</error>');
            exit;
        }

        $output->writeln('Building planning...');

        $yaml = new Parser();
        $planningConfig = $yaml->parse(file_get_contents(__DIR__ . '/config.yml'));
        $planningConfig['startDate'] = $input->getOption('startDate');
        $planningConfig['endDate'] = $input->getOption('endDate');

        $planningService = new PlanningService($planningConfig);
        $planning = $planningService->getTeamAvailabilities();

        // render planning
        $totalMdsAvailable = 0;
        $weekSubtotal = null;

        foreach($planning as $date => $teamAvailability) {
            if (date('N', strtotime($date)) === '1') {
                if (!is_null($weekSubtotal)) {
                    $output->writeln('------------------');
                    $output->writeln('Subtotal ' . ($weekSubtotal < 10 ? ' ' : '')  . '  <info>' . $weekSubtotal . ' MD</info>');
                    $weekSubtotal = 0;
                }

                $output->writeln('');
                $output->writeln('<fg=black;options=underscore>WK ' . date('W', strtotime($date)) . '</fg=black;options=underscore>');
            }

            if (number_format(array_sum($teamAvailability['team']), 1) !== '0.0') {
                $output->writeln($date . ': <info>' . number_format(array_sum($teamAvailability['team']), 1) . ' MD</info> ' . $teamAvailability['dateComments']);
            }
            $totalMdsAvailable += array_sum($teamAvailability['team']);

            $weekSubtotal += array_sum($teamAvailability['team']);
        }

        $output->writeln('------------------');
        $output->writeln('Subtotal ' . ($weekSubtotal < 10 ? ' ' : '')  . '  <info>' . $weekSubtotal . ' MD</info>');
        $output->writeln('');
        $output->writeln('<fg=black;options=underscore>                  </fg=black;options=underscore>');
        $output->writeln('Total:     <info>' . $totalMdsAvailable . ' MD</info>');
    }

    protected function isValidDateRange($startDate, $endDate)
    {
        $dateDiff = date_diff(new \DateTime($startDate), new \DateTime($endDate), false);

        return strtotime($startDate) !== false &&
               strtotime($endDate) !== false &&
               $dateDiff->days > 0 &&
               $dateDiff->invert === 0;
    }
}
