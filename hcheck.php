<?php
/* Host checker cli v0.1 by WTERH 06.2018 vk.com/id11053856 github.com/wterh/hcheck */
if (php_sapi_name() == 'cli') {
    // log files
    $LOG = array("base_path" => "logs/", "site_error" => "site_error.log", "page_check" => "page_check.log");
    // log func
    function log_siteerr($date, $site, $err) {
        global $LOG;
        $MSG = $date." | ".$site." | ".$err.PHP_EOL;
        file_put_contents($LOG['base_path'].$LOG['site_error'], $MSG, FILE_APPEND | LOCK_EX);
    }
    function log_sitecheck($date, $site) {
        global $LOG;
        $MSG = $date." | ".$site.PHP_EOL;
        file_put_contents($LOG['base_path'].$LOG['page_check'], $MSG, FILE_APPEND | LOCK_EX);
    }
    if (isset($argv[1]) and $argv[1] != '') {
        // console answer
        screen("notif","STARTUP", $argv[1]);
        $domain = $argv[1];
        if($domain){
            $check = curl_init($domain);
            curl_setopt ($check, CURLOPT_RETURNTRANSFER,false);
            curl_setopt ($check, CURLOPT_HEADER, false);
            curl_setopt ($check, CURLOPT_VERBOSE, false);
            curl_setopt ($check, CURLOPT_NOBODY,true);
            curl_exec   ($check); // Check connection protocol
            $redir = curl_getinfo($check, CURLINFO_RESPONSE_CODE);
            if($redir == "301" || $redir == "302") {
                screen("err","REDIRECT","Oops, 301/302 redirect, try to reconnect");
                $domain = "https://".$domain;
                $curl = curl_init($domain);

                $response = [];
                $response['autoreferer']   = curl_setopt  ($curl, CURLOPT_AUTOREFERER, true);
                $response['follow']        = curl_setopt  ($curl, CURLOPT_FOLLOWLOCATION, true);
                $response['crlf']          = curl_setopt  ($curl, CURLOPT_CRLF, true);
                $response['nobody']        = curl_setopt  ($curl, CURLOPT_NOBODY, false);
                $response['postred']       = curl_setopt  ($curl, CURLOPT_POSTREDIR, 3);
                $response['maxred']        = curl_setopt  ($curl, CURLOPT_MAXREDIRS, 5);
                $response['conntimeout']   = curl_setopt  ($curl, CURLOPT_CONNECTTIMEOUT, 30);
                $response['header']        = curl_setopt  ($curl, CURLOPT_HEADER, 0);
                $response['verbose']       = curl_setopt  ($curl, CURLOPT_VERBOSE, false);
                $response['transfer']      = curl_setopt  ($curl, CURLOPT_RETURNTRANSFER, true);
                $response['headersize']    = curl_getinfo ($curl);
                $response['error']         = curl_error   ($curl);
                $response['exec']          = curl_exec    ($curl); // Get server answer
                $body = $response['exec']; // get page contents

                // GREP PAGE
                $tags = [];
                preg_match    ('/<title>(.*)<\/title>/iu',                                      $body, $tags['title']);
                preg_match_all('/<meta.*content="(.*)".*>/isU',                                 $body, $tags['meta']);
                preg_match_all('/<link rel="(icon|favicon|shortcut icon)".*href="(.*)".*>/isU', $body, $tags['favicon']);
                preg_match_all('/<img.*src="(.*)".*>/isU',                                      $body, $tags['img']);
                preg_match_all('/<link.*rel="stylesheet".*href="(.*)".*>/isU',                  $body, $tags['css']);
                preg_match_all('/<script.*src="(.*)".*>/isU',                                   $body, $tags['js']);
                preg_match_all('/<h1.*>(.*)<\/h1>/isU',                                         $body, $tags['h1']);

                // GREP PHP ERROR
                $error = [];
                preg_match_all('/Notice:/iu',      $body, $error['notice']);
                preg_match_all('/Warning:/iu',     $body, $error['warning']);
                preg_match_all('/Fatal error:/iu', $body, $error['fatal']);

                // TITLE CHECK
                if(isset($tags['title']) && $tags['title'] != NULL) {
                    screen("notif","TITLE", $tags['title'][1]);
                }
                // META TAGS CHECK
                if(isset($tags['meta']) && $tags['meta'] != NULL) {
                    foreach($tags['meta'][1] as $mkey => $mval){
                        screen("notif","META", $mval);
                    }
                }
                if(isset($tags['favicon']) && $tags['favicon'] != NULL) {
                    foreach($tags['favicon'][2] as $mkey => $mval){
                        screen("notif","FAVICON", $mval);
                    }
                }
                if(isset($tags['css']) && $tags['css'] != NULL) {
                    foreach($tags['css'][1] as $mkey => $mval){
                        screen("notif","CSS", $mval);
                    }
                }
                if(isset($tags['h1']) && $tags['h1'] != NULL) {
                    foreach($tags['h1'][1] as $mkey => $mval){
                        screen("notif","H1", $mval);
                    }
                }
                // error search
                if(isset($error['notice']) && $error['notice'] != NULL) {
                    screen("err","NOTICE", "\x1b[0mFound \x1b[1;31m".count($error['notice'][0])."\x1b[0m");
                    log_siteerr(date("H:i:s"), $domain, $error['notice']);
                }
                if(isset($error['warning']) && $error['warning'] != NULL) {
                    screen("err","WARNING", "\x1b[0mFound \x1b[1;31m".count($error['warning'][0])."\x1b[0m");
                    log_siteerr(date("H:i:s"), $domain, $error['warning']);
                }
                if(isset($error['fatal']) && $error['fatal'] != NULL) {
                    screen("err","FATAL", "\x1b[0mFound \x1b[1;31m".count($error['fatal'][0])."\x1b[0m");
                    log_siteerr(date("H:i:s"), $domain, $error['fatal']);
                }

                // seo search
                if(isset($domain) && $domain != NULL) {
                    if($redir == "301" || $redir == "302") {
                        $robots = file_get_contents($domain."/robots.txt");
                        screen("notif", "ROBOTS", "Robots.txt found");
                    }
                    else { screen("err","ROBOTS","Robots not found"); }
                }

                if(isset($domain) && $domain != NULL) {
                    if($redir == "301" || $redir == "302") {
                        $robots = file_get_contents($domain."/sitemap.xml");
                        screen("notif", "SITEMAP", "Sitemap.xml found");
                    }
                    else { screen("err","SITEMAP","Sitemap.xml not found"); }
                }

                // Check main page on Google Page Speed Insight
                if($domain){
                    $gps = "https://www.googleapis.com/pagespeedonline/v4/runPagespeed?url=".$domain."&strategy=mobile&locale=ru&key=APIKEY";
                    $curl = curl_init($domain);
                    $response = [];
                    $response['url']           = curl_setopt  ($curl, CURLOPT_URL, $gps);
                    $response['autoreferer']   = curl_setopt  ($curl, CURLOPT_AUTOREFERER, true);
                    $response['follow']        = curl_setopt  ($curl, CURLOPT_FOLLOWLOCATION, true);
                    $response['crlf']          = curl_setopt  ($curl, CURLOPT_CRLF, true);
                    $response['nobody']        = curl_setopt  ($curl, CURLOPT_NOBODY, false);
                    $response['postred']       = curl_setopt  ($curl, CURLOPT_POSTREDIR, 3);
                    $response['maxred']        = curl_setopt  ($curl, CURLOPT_MAXREDIRS, 5);
                    $response['conntimeout']   = curl_setopt  ($curl, CURLOPT_CONNECTTIMEOUT, 30);
                    $response['header']        = curl_setopt  ($curl, CURLOPT_HEADER, 0);
                    $response['verbose']       = curl_setopt  ($curl, CURLOPT_VERBOSE, false);
                    $response['transfer']      = curl_setopt  ($curl, CURLOPT_RETURNTRANSFER, true);
                    $response['headersize']    = curl_getinfo ($curl);
                    $response['error']         = curl_error   ($curl);
                    $response['exec']          = curl_exec    ($curl); // Get server answer
                    $presult = $response['exec']; // get page contents
                    $result = json_decode($presult);
                    screen("google", null, "SPEED: ".$result->ruleGroups->SPEED->score."/100");
                    //screen("google", null, "RESPONSE: ".$result->formattedResults->ruleResults->MainResourceServerResponseTime->summary->format);
                }

                screen("notif","METHOD", "This try to HTTPS");
                screen("notif","FINISH", "Finish, see logs)");
                log_sitecheck(date("H:i:s"), $domain);
                curl_close($curl);
            } else {
                screen("err","REDIRECT","Oops, 301/302 redirect, try to reconnect");
                $domain = "http://".$domain;
                $curl = curl_init($domain);

                $response = [];
                $response['autoreferer']   = curl_setopt  ($curl, CURLOPT_AUTOREFERER, true);
                $response['follow']        = curl_setopt  ($curl, CURLOPT_FOLLOWLOCATION, true);
                $response['crlf']          = curl_setopt  ($curl, CURLOPT_CRLF, true);
                $response['nobody']        = curl_setopt  ($curl, CURLOPT_NOBODY, false);
                $response['postred']       = curl_setopt  ($curl, CURLOPT_POSTREDIR, 3);
                $response['maxred']        = curl_setopt  ($curl, CURLOPT_MAXREDIRS, 5);
                $response['conntimeout']   = curl_setopt  ($curl, CURLOPT_CONNECTTIMEOUT, 30);
                $response['header']        = curl_setopt  ($curl, CURLOPT_HEADER, 0);
                $response['verbose']       = curl_setopt  ($curl, CURLOPT_VERBOSE, false);
                $response['transfer']      = curl_setopt  ($curl, CURLOPT_RETURNTRANSFER, true);
                $response['headersize']    = curl_getinfo ($curl);
                $response['error']         = curl_error   ($curl);
                $response['exec']          = curl_exec    ($curl); // Get server answer
                $body = $response['exec']; // get page contents

                // GREP PAGE
                $tags = [];
                preg_match    ('/<title>(.*)<\/title>/iu',                                      $body, $tags['title']);
                preg_match_all('/<meta.*content="(.*)".*>/isU',                                 $body, $tags['meta']);
                preg_match_all('/<link rel="(icon|favicon|shortcut icon)".*href="(.*)".*>/isU', $body, $tags['favicon']);
                preg_match_all('/<img.*src="(.*)".*>/isU',                                      $body, $tags['img']);
                preg_match_all('/<link.*rel="stylesheet".*href="(.*)".*>/isU',                  $body, $tags['css']);
                preg_match_all('/<script.*src="(.*)".*>/isU',                                   $body, $tags['js']);
                preg_match_all('/<h1.*>(.*)<\/h1>/isU',                                         $body, $tags['h1']);

                // GREP PHP ERROR
                $error = [];
                preg_match_all('/Notice:/iu',      $body, $error['notice']);
                preg_match_all('/Warning:/iu',     $body, $error['warning']);
                preg_match_all('/Fatal error:/iu', $body, $error['fatal']);

                // TITLE CHECK
                if(isset($tags['title']) && $tags['title'] != NULL) {
                    screen("notif","TITLE", $tags['title'][1]);
                }
                // META TAGS CHECK
                if(isset($tags['meta']) && $tags['meta'] != NULL) {
                    foreach($tags['meta'][1] as $mkey => $mval){
                        screen("notif","META", $mval);
                    }
                }
                if(isset($tags['favicon']) && $tags['favicon'] != NULL) {
                    foreach($tags['favicon'][2] as $mkey => $mval){
                        screen("notif","FAVICON", $mval);
                    }
                }
                if(isset($tags['css']) && $tags['css'] != NULL) {
                    foreach($tags['css'][1] as $mkey => $mval){
                        screen("notif","CSS", $mval);
                    }
                }
                if(isset($tags['h1']) && $tags['h1'] != NULL) {
                    foreach($tags['h1'][1] as $mkey => $mval){
                        screen("notif","H1", $mval);
                    }
                }
                // error search
                if(isset($error['notice']) && $error['notice'] != NULL) {
                    screen("err","NOTICE", "\x1b[0mFound \x1b[1;31m".count($error['notice'][0])."\x1b[0m");
                    log_siteerr(date("H:i:s"), $domain, $error['notice']);
                }
                if(isset($error['warning']) && $error['warning'] != NULL) {
                    screen("err","WARNING", "\x1b[0mFound \x1b[1;31m".count($error['warning'][0])."\x1b[0m");
                    log_siteerr(date("H:i:s"), $domain, $error['warning']);
                }
                if(isset($error['fatal']) && $error['fatal'] != NULL) {
                    screen("err","FATAL", "\x1b[0mFound \x1b[1;31m".count($error['fatal'][0])."\x1b[0m");
                    log_siteerr(date("H:i:s"), $domain, $error['fatal']);
                }

                // seo search
                if(isset($domain) && $domain != NULL) {
                    if($redir == "301" || $redir == "302") {
                        $robots = file_get_contents($domain."/robots.txt");
                        screen("notif", "ROBOTS", "Robots.txt found");
                    }
                    else { screen("err","ROBOTS","Robots not found"); }
                }

                if(isset($domain) && $domain != NULL) {
                    if($redir == "301" || $redir == "302") {
                        $robots = file_get_contents($domain."/sitemap.xml");
                        screen("notif", "SITEMAP", "Sitemap.xml found");
                    }
                    else { screen("err","SITEMAP","Sitemap.xml not found"); }
                }

                // Check main page on Google Page Speed Insight
                if($domain){
                    $psm = "https://www.googleapis.com/pagespeedonline/v4/runPagespeed?url=".$domain."&strategy=mobile&locale=ru&key=AIzaSyBrJ0ZPp0-X3PySWnVv8cVKFsdkwgbWQtU";
                    $curl = curl_init($domain);
                    $response = [];
                    $response['url']           = curl_setopt  ($curl, CURLOPT_URL, $psm);
                    $response['autoreferer']   = curl_setopt  ($curl, CURLOPT_AUTOREFERER, true);
                    $response['follow']        = curl_setopt  ($curl, CURLOPT_FOLLOWLOCATION, true);
                    $response['crlf']          = curl_setopt  ($curl, CURLOPT_CRLF, true);
                    $response['nobody']        = curl_setopt  ($curl, CURLOPT_NOBODY, false);
                    $response['postred']       = curl_setopt  ($curl, CURLOPT_POSTREDIR, 3);
                    $response['maxred']        = curl_setopt  ($curl, CURLOPT_MAXREDIRS, 5);
                    $response['conntimeout']   = curl_setopt  ($curl, CURLOPT_CONNECTTIMEOUT, 30);
                    $response['header']        = curl_setopt  ($curl, CURLOPT_HEADER, 0);
                    $response['verbose']       = curl_setopt  ($curl, CURLOPT_VERBOSE, false);
                    $response['transfer']      = curl_setopt  ($curl, CURLOPT_RETURNTRANSFER, true);
                    $response['headersize']    = curl_getinfo ($curl);
                    $response['error']         = curl_error   ($curl);
                    $response['exec']          = curl_exec    ($curl); // Get server answer
                    $presult = $response['exec']; // get page contents
                    $result = json_decode($presult);
                    screen("google", null, "SPEED: ".$result->ruleGroups->SPEED->score."/100");
                    //screen("google", null, "RESPONSE: ".$result->formattedResults->ruleResults->MainResourceServerResponseTime->summary->format);
                }

                screen("notif","METHOD", "This try to HTTP");
                screen("notif","FINISH", "Finish, see logs)");
                log_sitecheck(date("H:i:s"), $domain);
                curl_close($curl);
            }
        } else {screen("err","ERROR","Whoopsi!");}
    } else {screen("err","ERROR","You forgot the domain!");}
} else {screen("err","ERROR","App will not work without cli");}

function screen($type, $title, $text) {
    if($type == "err") { $msg = "[\x1b[0;37m".date("H:i:s")."\x1b[0m][\x1b[1;31m".$title."\x1b[0m]: \x1b[4;31m".$text."\x1b[0m".PHP_EOL; }
    else if($type == "notif") { $msg = "[\x1b[0;37m".date("H:i:s")."\x1b[0m][\x1b[1;32m".$title."\x1b[0m]: \x1b[4;37m".$text."\x1b[0m".PHP_EOL; }
    else if($type == "google") { $msg = "[\x1b[0;37m".date("H:i:s")."][\x1b[0m\x1b[1;34mG\x1b[0m\x1b[1;31mO\x1b[0m\x1b[1;33mO\x1b[0m\x1b[1;34mG\x1b[0m\x1b[1;32mL\x1b[0m\x1b[1;31mE\x1b[0m]: \x1b[4;37m".$text."\x1b[0m".PHP_EOL; }
    else { $msg = "[\x1b[0;37m".date("H:i:s")."\x1b[0m][\x1b[1;35m".$title."\x1b[0m]: \x1b[4;31mSomething went wrong :(\x1b[0m".PHP_EOL; }
    echo $msg;
}
?>
