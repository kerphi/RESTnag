<?php

use Symfony\Component\HttpFoundation\Response;

$GLOBALS['root_path'][] = '/etc/nagiosgrapher/'; 

$app->get('/etc/nagiosgrapher/ngraph.d/', function() {
    $cfglist = glob('/etc/nagiosgrapher/ngraph.d/*.ncfg');

    ob_start();
    $xmlWriter = new XMLWriter();
    $xmlWriter->openUri('php://output');
    $xmlWriter->setIndent(true);
    include_once("ATOMWriter.php");
    $f = new ATOMWriter($xmlWriter, true);
    $f->startFeed('urn:restnag:etc-nagiosgrapher-ngraph.d')
        ->writeStartIndex(1)
        ->writeItemsPerPage(10)
        ->writeTotalResults(count($cfglist))
        ->writeTitle("/etc/nagios3/conf.d/");
    foreach($cfglist as $c) {
        $c = basename($c);
        $f->startEntry("urn:restnag:etc-nagiosgrapher-ngraph.d-".$c)
            ->writeTitle('/etc/nagiosgrapher/ngraph.d/'.$c)
            ->writeLink($GLOBALS['baseurl'].'/etc/nagiosgrapher/ngraph.d/'.urlencode($c), 'text/plain')
            ->endEntry();
    }

    $f->endFeed();
    $f->flush();
    $output = ob_get_contents();
    ob_end_clean();

    $r = new Response($output, 200);
    $r->headers->set('Content-Type', 'application/atom+xml; charset=UTF-8');
    return $r; 
});

// to check if nagiosgrapher is running:
// ps aux | grep `cat /var/run/nagiosgrapher/nagiosgrapher.pid` | grep ^nagios

$app->get('/etc/nagiosgrapher/', function() {
    $r = new Response('', 302);
    $r->headers->set('Location', $GLOBALS['baseurl'].'/etc/nagiosgrapher/ngraph.d/');
    return $r; 
});

