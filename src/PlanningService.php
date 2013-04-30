<?php

namespace src;

use \Google_Client;
use \Google_CalendarService;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PlanningService
{
    public function __construct($config)
    {
        $this->config = $config;
    }

    protected function getCalendarAccess()
    {
        $client = new Google_Client();
        $client->setApplicationName("Google Calendar PHP Starter Application");

        // Visit https://code.google.com/apis/console?api=calendar to generate your
        // client id, client secret, and to register your redirect uri.
        $client->setClientId($this->config['calClientId']);
        $client->setClientSecret($this->config['calClientSecret']);
        $client->setRedirectUri($this->config['calRedirectUri']);
        $cal = new Google_CalendarService($client);

        // get code by going to the URL given by:
//        die(var_dump($client->createAuthUrl()));
//        $code = "xxxxcode";
//        $client->authenticate($code);
//        $token = $client->getAccessToken();
//        die(var_dump($token));

        $client->setAccessToken($this->config['calToken']);

        return $cal;
    }

    public function getTeamAvailabilities()
    {
        $planning = array();

        $planningStartDate = date('N', strtotime('now')) == 1 ? date(DATE_RFC3339, strtotime('00:00:00', strtotime('now'))) : date(DATE_RFC3339, strtotime('last monday 00:00:00', strtotime('now')));
        $planningEndDate = date(DATE_RFC3339, strtotime($planningStartDate . ' +' . ($this->config['nbOfWeeks'] * 7 - 1) . ' day'));
        $planningDiff = date_diff(new \DateTime($planningStartDate), new \DateTime($planningEndDate), true);

        $cal = $this->getCalendarAccess();
        $teamEvents = $cal->events->listEvents($this->config['teamCalId'], array('timeMin' => $planningStartDate, 'timeMax' => $planningEndDate, 'singleEvents' => true));
        $liipEvents = $cal->events->listEvents($this->config['liipInternEventsCalId'], array('timeMin' => $planningStartDate, 'timeMax' => $planningEndDate, 'singleEvents' => true));

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
            if (in_array(date('N', $date), $this->config['daysToNotTaKeIntoAccount'])) { continue; }

            $planning[date('Y-m-d', $date)]['teamMembers'] = $this->config['billablePercentage'];
            $planning[date('Y-m-d', $date)]['dateComments'] = '';

            foreach ($events as $event) {
                foreach($this->config['teamMembers'] as $memberName) {
                    if ( ($this->isPersonalDayOff($event, $memberName) || $this->isLiipDayOff($event) || $this->isLiipInno($event)) &&
                        array_key_exists('date', $event['start']) &&
                        $event['start']['date'] === date('Y-m-d', $date) &&
                        $planning[date('Y-m-d', $date)]['teamMembers'][$memberName] > 0 // example: 1 Liip day off removed already. we don't want to remove it again if it is a person day off
                        ) {
                            $planning[date('Y-m-d', $date)]['teamMembers'][$memberName] -= $this->config['billablePercentage'][$memberName];

                            if ($this->isLiipDayOff($event) || $this->isLiipInno($event)) {
                                $planning[date('Y-m-d', $date)]['dateComments'] = '(' . $event['summary'] . ')';
                            }
                    }
                }
            }
        }

        return $planning;
    }

    protected function isPersonalDayOff($event, $memberName)
    {
        return strpos($event['summary'], $memberName . $this->config['personalDayOffString']) !== false;
    }

    protected function isLiipDayOff($event)
    {
        return strpos($event['summary'], $this->config['teamOffice']) !== false && strpos($event['summary'], $this->config['liipDayOffString']) !== false;
    }

    protected function isLiipInno($event)
    {
        return strpos($event['summary'], $this->config['innoDayOffString']) !== false;
    }
}
