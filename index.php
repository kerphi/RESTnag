<?php

require_once __DIR__.'/config.php'; 
require_once __DIR__.'/silex.phar'; 
use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application(); 

function parse_nagios_config() {
    $i = 0;
    $cfg = array();
    $lnum = 0;
    foreach(file('/etc/nagios3/nagios.cfg') as $l) {
        $lnum++;
        $l = trim($l);
        if ($l[0] == '#' or empty($l)) {
            continue;
        }
        $l = explode('=',$l);
        $l = array_map('trim', $l);
        if (isset($cfg[$l[0]])) {
            $cfg[$l[0]][] = array($lnum, $l[1]);
        } else {
            $cfg[$l[0]]   = array(array($lnum, $l[1]));
        }
    }
    return $cfg;
}

$app->get('/config/nagios.cfg/{v}/{n}', function($v, $n) {
    $cfg = parse_nagios_config();
    if (isset($cfg[$v][$n])) {
        $r = new Response($cfg[$v][$n][1], 200);
        $r->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        return $r; 
    } else {
        return new Response('', 404);
    }
});

$app->get('/config/nagios.cfg/', function() {
    $cfg = parse_nagios_config();

    ob_start();
    $xmlWriter = new XMLWriter();
    $xmlWriter->openUri('php://output');
    $xmlWriter->setIndent(true);
    include_once("ATOMWriter.php");
    $f = new ATOMWriter($xmlWriter, true);
    $f->startFeed('urn:restnag:config:nagios.cfg')
        ->writeStartIndex(1)
        ->writeItemsPerPage(10)
        ->writeTotalResults(1)
        ->writeTitle("nagios.cfg");
    foreach($cfg as $k => $v) {
        $i = 0;
        foreach($v as $vv) {
            $f->startEntry("urn:restnag:config:nagios.cfg:".$k.':'.$i)
                ->writeTitle($k.'_'.$i)
                ->writeLink(urlencode($k).'/'.$i, 'text/plain')
                ->writeContent($vv[1], 'text/plain')
                ->endEntry();
            $f->flush();
            $i++;
        }
    }

    $f->endFeed();
    $f->flush();
    $output = ob_get_contents();
    ob_end_clean();

    $r = new Response($output, 200);
    $r->headers->set('Content-Type', 'application/atom+xml; charset=UTF-8');
    return $r; 
});

$app->get('/config/', function() {

    ob_start();
    $xmlWriter = new XMLWriter();
    $xmlWriter->openUri('php://output');
    $xmlWriter->setIndent(true);
    include_once("ATOMWriter.php");
    $f = new ATOMWriter($xmlWriter, true);
    $f->startFeed('urn:restnag')
        ->writeStartIndex(1)
        ->writeItemsPerPage(10)
        ->writeTotalResults(1)
        ->writeTitle("Nagios's configuration");

    $f->startEntry("urn:restnag:config:nagios.cfg")
        ->writeTitle('nagios.cfg')
        ->writeLink("nagios.cfg/", 'application/atom+xml')
        ->endEntry();
    $f->flush();

    $f->startEntry("urn:restnag:config:conf.d")
        ->writeTitle('conf.d')
        ->writeLink("conf.d/", 'application/atom+xml')
        ->endEntry();
    $f->flush();

    $f->endFeed();
    $f->flush();  

    $r = new Response('', 200);
    $r->headers->set('Content-Type', 'application/atom+xml; charset=UTF-8');
    return $r; 
});

$app->get('/', function() { 

    $xmlWriter = new XMLWriter();
    $xmlWriter->openUri('php://output');
    $xmlWriter->setIndent(true);
    include_once("ATOMWriter.php");
    $f = new ATOMWriter($xmlWriter, true);
    $f->startFeed('urn:restnag')
        ->writeStartIndex(1)
        ->writeItemsPerPage(10)
        ->writeTotalResults(1)
        ->writeTitle($GLOBALS['title']);

    $f->startEntry("urn:restnag:config")
        ->writeTitle("Nagios's configuration")
        ->writeLink("config/", 'application/atom+xml')
        ->endEntry();
    $f->endFeed();
    $f->flush();  
    $output = ob_get_contents();
    ob_end_clean();

    $r = new Response($output, 200);
    $r->headers->set('Content-Type', 'application/atom+xml; charset=UTF-8');
    return $r; 
}); 

$app->run();
