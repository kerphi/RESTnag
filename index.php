<?php

require_once __DIR__.'/config.php'; 
require_once __DIR__.'/silex.phar'; 
use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application(); 

function patch_nagios_config($lnum, $key, $newvalue) {
    $patched = false;
    $lnum_c = 0;
    $cfg = array();
    foreach(file('/etc/nagios3/nagios.cfg') as $l) {
        $lnum_c++;
        $l = trim($l);
        if ($lnum == $lnum_c and $l[0] != '#' and !empty($l)) {
            $l2 = explode('=',$l);
            $l2 = array_map('trim', $l2);
            if ($l2[0] == $key) {
                $l2[1] = $newvalue;
                $cfg[] = implode('=', $l2);
                $patched = true;
            } else {
                $cfg[] = $l;
            }
        } else {
            $cfg[] = $l;
        }
    }
    return array('patched' => $patched, 'config' => $cfg);
}

function parse_nagios_config() {
    $i = 0;
    $cfg = array();
    $lnum = 0;
    foreach(file('/etc/nagios3/nagios.cfg') as $l) {
        $lnum++;
        $l = trim($l);
        if (empty($l) or $l[0] == '#') {
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

$app->put('/config/nagios.cfg/{v}/{n}', function($v, $n) use ($app) {
    $request  = $app['request'];
    $newvalue = $request->getContent();

    $cfg = parse_nagios_config();    
    if (isset($cfg[$v][$n])) {
        $output  = 'Line:     '.$cfg[$v][$n][0]."\n";
        $output .= 'Key:      '.$v."\n";
        $output .= 'OldValue: '.$cfg[$v][$n][1]."\n";

        $ret = patch_nagios_config($cfg[$v][$n][0], $v, $newvalue);
        if (!$ret['patched']) {
            return new Response('', 304); // not modified
        }

        // check new config syntaxe
        $tmpcfg = tempnam("/tmp", "nagios");
        file_put_contents($tmpcfg, implode("\n", $ret['config']));
        exec('/usr/sbin/nagios3 -v '.$tmpcfg, $o,  $r);
        if ($r == 0) {
            $backup = file_get_contents('/etc/nagios3/nagios.cfg');
            $r      = copy($tmpcfg, '/etc/nagios3/nagios.cfg');
            if ($r) {
                // restart nagios daemon
                exec('sudo /etc/init.d/nagios3 reload', $o, $r);
                if ($r == 0) {
                    $status = array(implode("\n", $o), 200);
                } else {
                    $status = array(implode("\n", $o), 422);
                    // restore backup
                    file_put_contents('/etc/nagios3/nagios.cfg', $backup);
                    exec('sudo /etc/init.d/nagios3 reload', $o, $r);
                }
            } else {
                $status = array('Unable to write on /etc/nagios3/nagios.cfg', 500);
            }
        } else {
            $status = array(implode("\n", $o), 422); // Unprocessable Entity
        }
        unlink($tmpcfg);

        $r = new Response($status[0], $status[1]);
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
