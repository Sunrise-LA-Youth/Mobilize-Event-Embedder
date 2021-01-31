<?php

require_once('vendor/autoload.php');

// Global variables
$mobilizeTagId = 1055;
$hubMeetingId = 274177;

// Initialize Mobilize API call
$client = new GuzzleHttp\Client();
$url = "https://api.mobilize.us/v1/organizations/2425/events";

// Build UTM query to track where people are coming from (any UTM GET parameters on this script are passed on to Mobilize links)
$utm = array(
    'utm_source' => (!isset($_GET['utm_source']) || $_GET['utm_source'] == '') ? '' : $_GET['utm_source'],
    'utm_medium' => (!isset($_GET['utm_medium']) || $_GET['utm_medium'] == '') ? '' : $_GET['utm_medium'],
    'utm_campaign' => (!isset($_GET['utm_campaign']) || $_GET['utm_campaign'] == '') ? '' : $_GET['utm_campaign'],
    'utm_term' => (!isset($_GET['utm_term']) || $_GET['utm_term'] == '') ? '' : $_GET['utm_term'],
    'utm_content' => (!isset($_GET['utm_content']) || $_GET['utm_content'] == '') ? '' : $_GET['utm_content']
);
$utm = http_build_query(array_filter($utm));

// Get/set output format
$format = (!isset($_GET['format']) || $_GET['format'] == '') ? 'json' : $_GET['format'];

// Get all public events tagged with the LA Youth tag
if (!isset($_GET['action']) || (isset($_GET['action']) && $_GET['action'] == 'all')) {
    $start = (!isset($_GET['start']) || $_GET['start'] == '') ? 0 : $_GET['start'];
    $limit = (!isset($_GET['limit']) || $_GET['limit'] == '') ? 3 : $_GET['limit'];
    $url .= "?exclude_full=true&tag_id=".$mobilizeTagId;
    
    $res = $client->request('GET', $url);
    $json = json_decode($res->getBody());
    
    $events = $json->data;
    
    $filteredEvents = array();
    
    foreach ($events as $event) {
        foreach ($event->timeslots as $time) {
            if ($time->start_date >= time()) {
                $newEvent = new stdClass();
                $newEvent->id = $time->id;
                $newEvent->start_unix = $time->start_date;
                
                $la_time = new DateTimeZone($event->timezone);
                $datetime = new DateTime();
                $datetime->setTimestamp($time->start_date);
                $datetime->setTimezone($la_time);
                $newEvent->start = $datetime->format('Y-m-d H:i:s');
                
                $newEvent->event_id = $event->id;
                $newEvent->name = str_replace('Sunrise Los Angeles Youth','',$event->title);
                $newEvent->url = ($utm == '') ? $event->browser_url . '?timeslot=' . $newEvent->id : $event->browser_url . '?timeslot=' . $newEvent->id . '&' . $utm;
                $newEvent->image = $event->featured_image_url;
                $newEvent->timezone = $event->timezone;
                $filteredEvents[] = $newEvent;
            }
        }
        
    }
    // Filter events by start date (newest first)
    usort($filteredEvents, function($a, $b) {
        return $a->start_unix <=> $b->start_unix;
    });
    
    // Only get soonest $limit events
    $events = array_slice($filteredEvents, $start, $limit);
    
    if (count($events) > 0) { // Only if events
        if ($format == 'json') {
            print_r(json_encode($events));
        }
        elseif ($format == 'html') {
            $html = <<<HTML
<link rel='stylesheet' id='bootstrap-css' href='https://www.sunriseyouth.la/wp-content/themes/visual-composer-starter/css/bootstrap.min.css?ver=3.3.7' type='text/css' media='all'/>
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:600,900%7CSource+Serif+Pro" media="all">


<div class="container-fluid" id="mobilizeEvents">
    <div class="row">
HTML;
            foreach ($events as $event) {
                $html .= <<<HTML
            <div class="col-sm-4">
                <div class="event">
                    <strong>$event->name</strong>
                    <span class="time">
HTML;
                        // Date magic
                        $la_time = new DateTimeZone($event->timezone);
                        $date = new DateTime();
                        $date->setTimezone($la_time);
                        $datetime = new DateTime($event->start);
                        $datetime->setTimezone($la_time);
                        
                        $dateToday = $date->format('Y-m-d');
                        $datetime2 = $datetime->format('Y-m-d');
                        
                        $dateTmrw = $date->modify('+1 day');
                        $dateTmrw = $dateTmrw->format('Y-m-d');

                        if($dateToday == $datetime2) {
                            $html .= ' Today &bull; ' . $datetime->format('g:i A T');
                        }
                        elseif($dateTmrw == $datetime2) {
                            $html .= ' Tomorrow &bull; ' . $datetime->format('g:i A T');
                        } else {
                            $html .= $datetime->format('l, F j') . ' &bull; ' . $datetime->format('g:i A T');
                        }
                    $html .= <<<HTML
                    </span>
                    <a href="$event->url" target="_blank" class="btn btn-primary btn-event stretched-link">More Info</a>
                </div>
            </div>
HTML;
            }
            $html .= <<<HTML
    </div>            
</div>
<style>
    #mobilizeEvents .event {
        font-family: Source Sans Pro, sans-serif;
        font-weight: 600;
        border-radius: 4px;
        background: #E5ECE0;
        width: 100%;
        padding: 30px;
        color: #33342e;
        font-size: 18px;
        text-transform: uppercase;
        margin-bottom: 15px;
    }
    #mobilizeEvents .event strong {
        font-size: 22px;
        font-weight: 900;
        display: block;
    }
    #mobilizeEvents .event .btn-event {
        margin-top: 40px;
        border-radius: 100px;
        padding: 5px 20px;
        background: #FFDE16;
        border: 0;
        color: #33342e;
        display: block;
    }
    #mobilizeEvents .event .btn-event:hover {
        background: #EFCE18;
    }
    .stretched-link::after {
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 1;
        pointer-events: auto;
        content: "";
        background-color: rgba(0,0,0,0);
    }
    @media (min-width: 768px) {
        #mobilizeEvents .row {
            display: flex;
            align-items: stretch;
        }
        #mobilizeEvents .row > div {
            display: flex;
            align-items: stretch;
        }
        #mobilizeEvents .event {
            display: flex;
            flex-direction: column;
        }
        #mobilizeEvents .event span {
            flex-grow: 1;
        }
    }
</style>
HTML;
        ?>
jQuery('#mobilizeEventContainer').html("<?php echo str_replace(array("\n", "\r"), ' ', addslashes($html)); ?>");
        <?php
        }
        
    }
    
}
// Get next hub meeting time
elseif (isset($_GET['action']) && $_GET['action'] == 'nexthub') {
    $url = "https://api.mobilize.us/v1/events/".$hubMeetingId;
    
    $res = $client->request('GET', $url);
    $json = json_decode($res->getBody());
    
    $event = $json->data;
    
    $filteredEvents = array();
    
    foreach ($event->timeslots as $time) {
        if ($time->start_date >= time()) {
            $newEvent = new stdClass();
            $newEvent->id = $time->id;
            $newEvent->start_unix = $time->start_date;
            
            $la_time = new DateTimeZone($event->timezone);
            $datetime = new DateTime();
            $datetime->setTimestamp($time->start_date);
            $datetime->setTimezone($la_time);
            $newEvent->start = $datetime->format('Y-m-d H:i:s');
            
            $newEvent->url = ($utm == '') ? $event->browser_url . '?timeslot=' . $newEvent->id : $event->browser_url . '?timeslot=' . $newEvent->id . '&' . $utm;
            $newEvent->timezone = $event->timezone;
            $filteredEvents[] = $newEvent;
        }
    }
    
    // Sort timeslots with sonnest at beginning
    usort($filteredEvents, function($a, $b) {
        return $a->start_unix <=> $b->start_unix;
    });
    
    if (count($filteredEvents) > 0) {
        $event = $filteredEvents[0];
    
        if ($format == 'json') {
            print_r(json_encode($event));
        }
        elseif ($format == 'html') {
            $la_time = new DateTimeZone($event->timezone);
            $datetime = new DateTime($event->start);
            $datetime->setTimezone($la_time);
        
            $date = $datetime->format('l, F j');
            ?>
jQuery('.next-hub-meeting .vce-button--style-basic-icon-text').text("RSVP to our next hub meeting on <?php echo $date; ?>");
jQuery('.next-hub-meeting a').attr("href","<?php echo $event->url; ?>");
            <?php
        }
    }
    
}
elseif (isset($_GET['action']) && $_GET['action'] != '') {
    $url = "https://api.mobilize.us/v1/events/".$_GET['action'];
    $start = (!isset($_GET['start']) || $_GET['start'] == '') ? 1 : $_GET['start'];
    $limit = (!isset($_GET['limit']) || $_GET['limit'] == '') ? 3 : $_GET['limit'];
    
    $res = $client->request('GET', $url);
    $json = json_decode($res->getBody());
    
    $event = $json->data;
    
    $filteredEvents = array();
    
    foreach ($event->timeslots as $time) {
        if ($time->start_date >= time()) {
            $newEvent = new stdClass();
            $newEvent->id = $time->id;
            $newEvent->start_unix = $time->start_date;
            
            $la_time = new DateTimeZone($event->timezone);
            $datetime = new DateTime();
            $datetime->setTimestamp($time->start_date);
            $datetime->setTimezone($la_time);
            $newEvent->start = $datetime->format('Y-m-d H:i:s');
            
            $newEvent->url = ($utm == '') ? $event->browser_url . '?timeslot=' . $newEvent->id : $event->browser_url . '?timeslot=' . $newEvent->id . '&' . $utm;
            $newEvent->timezone = $event->timezone;
            $filteredEvents[] = $newEvent;
        }
    }
    
    // Sort by start date, soonest first
    usort($filteredEvents, function($a, $b) {
        return $a->start_unix <=> $b->start_unix;
    });
    
    if (count($filteredEvents) > 0) {
        $event = $filteredEvents[0];
    
        $events = array_slice($filteredEvents, $start, $limit);
    
        if ($format == 'json') {
            print_r(json_encode($event));
            print_r(json_encode($events));
        }
        elseif ($format == 'html') {
            $la_time = new DateTimeZone($event->timezone);
            $date = new DateTime();
            $date->setTimezone($la_time);
            $datetime = new DateTime($event->start);
            $datetime->setTimezone($la_time);
            
            $dateToday = $date->format('Y-m-d');
            $datetime2 = $datetime->format('Y-m-d');
            
            $dateTmrw = $date->modify('+1 day');
            $dateTmrw = $dateTmrw->format('Y-m-d');
    
            if($dateToday == $datetime2) {
                $dateHtml = 'Today at ' . $datetime->format('g:i A T');
            }
            elseif($dateTmrw == $datetime2) {
                $dateHtml = 'Tomorrow at ' . $datetime->format('g:i A T');
            }
            else {
                $dateHtml = $datetime->format('l, F j') . ' at ' . $datetime->format('g:i A T');
            }
            
            $html = '';
            foreach ($events as $futureEvent) {
                $html .= "<li><a href=\"{$futureEvent->url}\" target=\"_blank\">";
                
                $la_time = new DateTimeZone($futureEvent->timezone);
                $date = new DateTime();
                $date->setTimezone($la_time);
                $datetime = new DateTime($futureEvent->start);
                $datetime->setTimezone($la_time);
                
                $dateToday = $date->format('Y-m-d');
                $datetime2 = $datetime->format('Y-m-d');
                
                $dateTmrw = $date->modify('+1 day');
                $dateTmrw = $dateTmrw->format('Y-m-d');
        
                if($dateToday == $datetime2) {
                    $html .= 'Today &bull; ' . $datetime->format('g:i A T');
                }
                elseif($dateTmrw == $datetime2) {
                    $html .= 'Tomorrow &bull; ' . $datetime->format('g:i A T');
                }
                else {
                    $html .= $datetime->format('F j') . ' &bull; ' . $datetime->format('g:i A T');
                }
                
                $html .= "</a></li>";
            }
    
            ?>
jQuery('.next-meeting-date p span').text("<?php echo $dateHtml; ?>");
jQuery('.future-meetings ul').html("<?php echo str_replace(array("\n", "\r"), ' ', addslashes($html)); ?>");
            <?php
        }
    }
    else {
        ?>
jQuery('.next-meeting-date p span').text("NO MEETINGS SCHEDULED");
jQuery('.future-meetings ul').hide();
jQuery('.future-meeting-title').hide();
jQuery('.future-meeting-button').hide();
        <?php
        
    }
    
}
