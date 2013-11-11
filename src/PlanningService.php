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

        $planningStartDate = date(DATE_RFC3339, strtotime('00:00:00', strtotime($this->config['startDate'])));
        $planningEndDate = date(DATE_RFC3339, strtotime('23:59:59', strtotime($this->config['endDate'])));
        $planningDiff = date_diff(new \DateTime($planningStartDate), new \DateTime($planningEndDate), true);

        $cal = $this->getCalendarAccess();
        $liipAbsences = $cal->events->listEvents($this->config['liipAbsenceCalId'], array('timeMin' => $planningStartDate, 'timeMax' => $planningEndDate, 'singleEvents' => true));
        $liipEvents = $cal->events->listEvents($this->config['liipInternEventsCalId'], array('timeMin' => $planningStartDate, 'timeMax' => $planningEndDate, 'singleEvents' => true));

        if (array_key_exists('items', $liipAbsences) && array_key_exists('items', $liipEvents)) {
            $events = array_merge($liipAbsences['items'], $liipEvents['items']);
        } elseif (array_key_exists('items', $liipAbsences)) {
            $events = $liipAbsences['items'];
        } elseif (array_key_exists('items', $liipAbsences)) {
            $events = $liipEvents['items'];
        } else {
            $events = array();
        }

        for ($i = 0; $i <= $planningDiff->days; $i++) {
            $date = strtotime($planningStartDate . ' +' . $i . ' day');
            if (in_array(date('N', $date), $this->config['daysToNotTaKeIntoAccount'])) {
                $planning[date('Y-m-d', $date)]['team'] = array();
                $planning[date('Y-m-d', $date)]['dateComments'] = '';
                continue;
            }

            // prepare array template for every date so to decrease its number while finding days off in calendar
            // ex: team > Dorian = 0.8
            //          > Laurent = 0.5
            //          > Germain = 0.5
            $billablePercentages = $this->config['team'];
            foreach($billablePercentages as $memberName => $memberInfos) {
                $billablePercentages[$memberName] = $memberInfos['billablePercentage'];
            }
            $planning[date('Y-m-d', $date)]['team'] = $billablePercentages;

            // date comment example: "FR/LS/ZH Feiertag: Nationalfeiertag / Fête Nationale"
            // goal is to help identify at a glance why date availabilities = 0 MD
            $planning[date('Y-m-d', $date)]['dateComments'] = '';

            foreach ($events as $event) {
                foreach(array_keys($this->config['team']) as $key => $memberName) {
                    if ( ($this->isPersonalDayOff($event, $this->config['team'][$memberName]['email']) || $this->isLiipDayOff($event) || $this->isLiipInno($event)) &&
                        array_key_exists('date', $event['start']) &&
                        array_key_exists('date', $event['end']) &&
                        date('Y-m-d', $date) >= $event['start']['date'] &&
                        date('Y-m-d', $date) < $event['end']['date'] &&
                        $planning[date('Y-m-d', $date)]['team'][$memberName] > 0 // example: 1 Liip day off removed already. we don't want to remove it again if it is a person day off
                        ) {
                            $planning[date('Y-m-d', $date)]['team'][$memberName] -= $this->config['team'][$memberName]['billablePercentage'];
                            if ($this->isLiipDayOff($event) || $this->isLiipInno($event)) {
                                $planning[date('Y-m-d', $date)]['dateComments'] = '(' . $event['summary'] . ')';
                            }
                    }
                }
            }
        }

        return $planning;
    }

    protected function isPersonalDayOff($event, $teamMemberEmail)
    {
        return ($event['creator']['email'] === $teamMemberEmail &&
                ($event['organizer']['email'] == $this->config['liipAbsenceCalId'] || // to prevent entries done by SSM in Events calendar
                 $this->hasAttendee($event, $this->config['liipAbsenceCalId']))
        );
    }

    protected function isLiipDayOff($event)
    {
        return strpos($event['summary'], $this->config['teamOffice']) !== false && strpos($event['summary'], $this->config['liipDayOffString']) !== false;
    }

    protected function isLiipInno($event)
    {
        return strpos($event['summary'], $this->config['innoDayOffString']) !== false;
    }

    protected function hasAttendee($event, $attendee)
    {
        if (!isset($event['attendees'])) {
            return false;
        }

        foreach ($event['attendees'] as $eventAttendee) {
            if ($eventAttendee['email'] === $attendee) {
                return true;
            }
        }

        return false;
    }
}
