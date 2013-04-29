<?php

namespace src;

use \Google_Client;
use \Google_CalendarService;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Planning
{
    public function __construct($teamMembers, $teamOffice, $offString, $liipDayOffString, $innoDayOffString, $numberOfWeeksToPlan, $daysToNotTaKeIntoAccount, $teamCalId, $liipInternEventsCalId, $calClientId, $calClientSecret, $calRedirectUri, $calToken)
    {
        $this->teamMembers = $teamMembers;
        $this->teamOffice = $teamOffice;
        $this->personalOffString = $offString;
        $this->liipDayOffString = $liipDayOffString;
        $this->innoDayOffString = $innoDayOffString;
        $this->numberOfWeeksToPlan = $numberOfWeeksToPlan;
        $this->daysToNotTaKeIntoAccount = $daysToNotTaKeIntoAccount;
        $this->teamCalId = $teamCalId;
        $this->liipInternEventsCalId = $liipInternEventsCalId;
        $this->calClientId = $calClientId;
        $this->calClientSecret = $calClientSecret;
        $this->calRedirectUri = $calRedirectUri;

        $this->cal = $this->getCalendarAccess($calToken);
        $this->planning = array();
    }

    protected function getCalendarAccess($token)
    {
        $client = new Google_Client();
        $client->setApplicationName("Google Calendar PHP Starter Application");

        // Visit https://code.google.com/apis/console?api=calendar to generate your
        // client id, client secret, and to register your redirect uri.
        $client->setClientId($this->calClientId);
        $client->setClientSecret($this->calClientSecret);
        $client->setRedirectUri($this->calRedirectUri);
        $cal = new Google_CalendarService($client);

        // get code by going to the URL given by:
//        die(var_dump($client->createAuthUrl()));
//        $code = "xxxxcode";
//        $client->authenticate($code);
//        $token = $client->getAccessToken();
//        die(var_dump($token));

        $client->setAccessToken($token);

        return $cal;
    }

    public function buildPlanning()
    {
        $planningStartDate = date('N', strtotime('now')) == 1 ? date(DATE_RFC3339, strtotime('00:00:00', strtotime('now'))) : date(DATE_RFC3339, strtotime('last monday 00:00:00', strtotime('now')));
        $planningEndDate = date(DATE_RFC3339, strtotime($planningStartDate . ' +' . ($this->numberOfWeeksToPlan * 7 - 1) . ' day'));
        $planningDiff = date_diff(new \DateTime($planningStartDate), new \DateTime($planningEndDate), true);

        $teamEvents = $this->cal->events->listEvents($this->teamCalId, array('timeMin' => $planningStartDate, 'timeMax' => $planningEndDate, 'singleEvents' => true));
        $liipEvents = $this->cal->events->listEvents($this->liipInternEventsCalId, array('timeMin' => $planningStartDate, 'timeMax' => $planningEndDate, 'singleEvents' => true));

        if (array_key_exists('items', $teamEvents) && array_key_exists('items', $liipEvents)) {
            $events = array_merge($teamEvents['items'], $liipEvents['items']);
        } elseif (array_key_exists('items', $teamEvents)) {
            $events = $teamEvents['items'];
        } elseif (array_key_exists('items', $liipEvents)) {
            $events = $liipEvents['items'];
        } else {
            $events = array();
        }

        for ($i = 0; $i <= $planningDiff->days; $i++) {
            $date = strtotime($planningStartDate . ' +' . $i . ' day');
            if (in_array(date('N', $date), $this->daysToNotTaKeIntoAccount)) { continue; }

            $this->planning[date('Y-m-d', $date)]['teamMembers'] = $this->getTeamMembersArraySkeleton();
            $this->planning[date('Y-m-d', $date)]['dateComments'] = '';

            foreach ($events as $event) {
                foreach($this->teamMembers as $memberName) {
                    if ( ($this->isPersonalDayOff($event, $memberName) || $this->isLiipDayOff($event) || $this->isLiipInno($event)) &&
                        array_key_exists('date', $event['start']) &&
                        $event['start']['date'] === date('Y-m-d', $date) &&
                        $this->planning[date('Y-m-d', $date)]['teamMembers'][$memberName] > 0 // example: 1 Liip day off removed already. we don't want to remove it again if it is a person day off
                        ) {
                            $this->planning[date('Y-m-d', $date)]['teamMembers'][$memberName] -= 1;

                            if ($this->isLiipDayOff($event) || $this->isLiipInno($event)) {
                                $this->planning[date('Y-m-d', $date)]['dateComments'] = '(' . $event['summary'] . ')';
                            }
                    }
                }
            }
        }
    }

    protected function getTeamMembersArraySkeleton()
    {
        return array_fill_keys(array_values($this->teamMembers), 1);
    }

    protected function isPersonalDayOff($event, $memberName)
    {
        return strpos($event['summary'], $memberName . $this->personalOffString) !== false;
    }

    protected function isLiipDayOff($event)
    {
        return strpos($event['summary'], $this->teamOffice) !== false && strpos($event['summary'], $this->liipDayOffString) !== false;
    }

    protected function isLiipInno($event)
    {
        return strpos($event['summary'], $this->innoDayOffString) !== false;
    }

    public function render($output, $outputType)
    {
        if ($outputType === 'console') {
            $this->renderPlanningOnConsole($output);
        } elseif ($outputType === 'html') {
            $this->renderPlanningAsHtml();
        } else {
            $output->writeln('Planning output specified not supported yet ;)');
        }
    }

    protected function renderPlanningOnConsole($output)
    {
        $totalMdsAvailable = 0;

        foreach($this->planning as $date => $teamAvailability) {
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

    protected function renderPlanningAsHtml()
    {
    }
}
