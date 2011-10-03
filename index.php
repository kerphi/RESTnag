<?php

require_once __DIR__.'/config.php'; 
require_once __DIR__.'/silex.phar'; 
use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application(); 

// load available modules
$GLOBALS['root_path'] = array();
foreach(glob(__DIR__.'/modules/*.php') as $m) {
    include $m;
}

$app->get('/etc/', function() { 

    $xmlWriter = new XMLWriter();
    $xmlWriter->openUri('php://output');
    $xmlWriter->setIndent(true);
    include_once("ATOMWriter.php");
    $f = new ATOMWriter($xmlWriter, true);
    $f->startFeed('urn:restnag:ect:')
        ->writeStartIndex(1)
        ->writeItemsPerPage(10)
        ->writeTotalResults(1)
        ->writeTitle('/etc/');

    foreach($GLOBALS['root_path'] as $p) {
        $p_exp = explode('/', $p);
        $f->startEntry('urn'.implode(':', $p_exp))
            ->writeTitle($p)
            ->writeLink($GLOBALS['baseurl'].$p, 'application/atom+xml')
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

$app->get('/', function() { 
    $r = new Response('', 302);
    $r->headers->set('Location', $GLOBALS['baseurl'].'/etc/');
    return $r; 
}); 

$app->run();
