<?php

require_once __DIR__.'/config.php'; 
require_once __DIR__.'/restnag.php'; 
require_once __DIR__.'/silex.phar'; 
use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application(); 

$app->get('/etc/nagios3/nagios.cfg/{v}/{n}', function($v, $n) {
    $cfg = parse_nagios_config();
    if (isset($cfg[$v][$n])) {
        $r = new Response($cfg[$v][$n][1], 200);
        $r->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        return $r; 
    } else {
        return new Response('', 404);
    }
});

$app->get('/etc/nagios3/nagios.cfg/{v}/', function($v) {
    $cfg = parse_nagios_config();
    if (isset($cfg[$v])) {
        $output = '';
        foreach($cfg[$v] as $vv) {
            $output .= $vv[1]."\n";
        }
        $r = new Response($output, 200);
        $r->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        return $r;
    } else {
        return new Response('', 404);
    }
});

$app->put('/etc/nagios3/nagios.cfg/{v}/{n}', function($v, $n) use ($app) {
    $request  = $app['request'];
    $newvalue = $request->getContent();
    settype($n, "integer");
    settype($v, "string");

    $cfg = parse_nagios_config();    
    if (!isset($cfg[$v]) && $n != 0) {
        return new Response('', 404);
    }
    $ret = patch_nagios_config(isset($cfg[$v][$n]) ? $cfg[$v][$n][0] : -1, $v, $newvalue);
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
});

$app->get('/etc/nagios3/nagios.cfg/', function() {
    $cfg = parse_nagios_config();

    ob_start();
    $xmlWriter = new XMLWriter();
    $xmlWriter->openUri('php://output');
    $xmlWriter->setIndent(true);
    include_once("ATOMWriter.php");
    $f = new ATOMWriter($xmlWriter, true);
    $f->startFeed('urn:restnag:etc-nagios3-nagios.cfg')
        ->writeStartIndex(1)
        ->writeItemsPerPage(10)
        ->writeTotalResults(1) // todo: calculate from $cfg
        ->writeTitle("/etc/nagios3/nagios.cfg/");
    foreach($cfg as $k => $v) {
        $i = 0;
        foreach($v as $vv) {
            $f->startEntry("urn:restnag:etc-nagios3-nagios.cfg-".$k.'-'.$i)
                ->writeTitle('/etc/nagios3/nagios.cfg/'.$k.'/'.$i)
                ->writeLink($GLOBALS['baseurl'].'/etc/nagios3/nagios.cfg/'.urlencode($k).'/'.$i, 'text/plain')
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

$app->put('/etc/nagios3/conf.d/{conf_file}', function($conf_file) use ($app) {
    $request = $app['request'];
    $config  = $request->getContent();
    $conf_file_path = '/etc/nagios3/conf.d/'.$conf_file;
    $is_config_new = !file_exists($conf_file_path);

    if (!preg_match('/\.cfg$/',$conf_file)) {
        return new Response('Bad format '.$conf_file.' must end with .cfg'."\n", 400);
    }


    // create a backup from the old config
    $backup = !$is_config_new ? file_get_contents($conf_file_path) : '';

    // test the new config
    file_put_contents($conf_file_path, $config);
    exec('/usr/sbin/nagios3 -v /etc/nagios3/nagios.cfg', $o,  $r);
    if ($r == 0) {
        // restart nagios daemon
        $o = array();
        exec('sudo /etc/init.d/nagios3 reload', $o, $r);
        if ($r == 0) {
            $status = array(implode("\n", $o), 200);
        } else {
            $status = array(implode("\n", $o), 422);
        }
    } else {
        $status = array(implode("\n", $o), 422); // Unprocessable Entity
    }

    // restore backup ?
    if ($status[1] != 200) {
        if ($is_config_new) {
            unlink($conf_file_path);
        } else {
            file_put_contents($conf_file_path, $backup);
        }
        exec('sudo /etc/init.d/nagios3 reload', $o, $r);
    }

    $r = new Response($status[0], $status[1]);
    $r->headers->set('Content-Type', 'text/plain; charset=UTF-8');
    return $r;
});

$app->delete('/etc/nagios3/conf.d/{conf_file}', function($conf_file) {
    $conf_file_path = '/etc/nagios3/conf.d/'.$conf_file;
    if (!file_exists($conf_file_path)) {
        return new Response($conf_file_path.' does not exists', 404);
    }
    if (!preg_match('/\.cfg$/',$conf_file)) {
        return new Response('Bad format '.$conf_file.' must end with .cfg'."\n", 400);
    }

    // create a backup from the config
    $backup = file_get_contents($conf_file_path);

    // test nagios with the removed config
    $r = unlink($conf_file_path);
    if (!$r) {
        return new Response('Unable to delete '.$conf_file_path, 403);
    }

    exec('/usr/sbin/nagios3 -v /etc/nagios3/nagios.cfg', $o,  $r);
    if ($r == 0) {
        // restart nagios daemon
        $o = array();
        exec('sudo /etc/init.d/nagios3 reload', $o, $r);
        if ($r == 0) {
            $status = array(implode("\n", $o), 200);
        } else {
            $status = array(implode("\n", $o), 422);
        }
    } else {
        $status = array(implode("\n", $o), 422); // Unprocessable Entity
    }

    // restore backup ?
    if ($status[1] != 200) {
        file_put_contents($conf_file_path, $backup);
        exec('sudo /etc/init.d/nagios3 reload', $o, $r);
    }

    $r = new Response($status[0], $status[1]);
    $r->headers->set('Content-Type', 'text/plain; charset=UTF-8');
    return $r;
});

$app->get('/etc/nagios3/conf.d/{conf_file}', function($conf_file) {
    $conf_file_path = '/etc/nagios3/conf.d/'.$conf_file;
    if (!file_exists($conf_file_path)) {
        return new Response($conf_file_path.' does not exists', 404);
    }
    $r = new Response(file_get_contents($conf_file_path), 200);
    $r->headers->set('Content-Type', 'text/plain; charset=UTF-8');
    return $r; 
});

$app->get('/etc/nagios3/conf.d/', function() {
    $cfglist = glob('/etc/nagios3/conf.d/*.cfg');

    ob_start();
    $xmlWriter = new XMLWriter();
    $xmlWriter->openUri('php://output');
    $xmlWriter->setIndent(true);
    include_once("ATOMWriter.php");
    $f = new ATOMWriter($xmlWriter, true);
    $f->startFeed('urn:restnag:etc-nagios3-conf.d')
        ->writeStartIndex(1)
        ->writeItemsPerPage(10)
        ->writeTotalResults(count($cfglist))
        ->writeTitle("/etc/nagios3/conf.d/");
    foreach($cfglist as $c) {
        $c = basename($c);
        $f->startEntry("urn:restnag:etc-nagios3-conf.d-".$c)
            ->writeTitle('/etc/nagios3/conf.d/'.$c)
            ->writeLink($GLOBALS['baseurl'].'/etc/nagios3/conf.d/'.urlencode($c), 'text/plain')
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

$app->get('/etc/nagios3/', function() {

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
        ->writeTitle("/etc/nagios3/");

    $f->startEntry("urn:restnag:etc-nagios3-nagios.cfg")
        ->writeTitle('/etc/nagios3/nagios.cfg/')
        ->writeLink($GLOBALS['baseurl']."/etc/nagios3/nagios.cfg/", 'application/atom+xml')
        ->endEntry();
    $f->flush();

    $f->startEntry("urn:restnag:etc-nagios3-conf.d")
        ->writeTitle('/etc/nagios3/conf.d/')
        ->writeLink($GLOBALS['baseurl']."/etc/nagios3/conf.d/", 'application/atom+xml')
        ->endEntry();
    $f->flush();

    $f->endFeed();
    $f->flush();  

    $r = new Response('', 200);
    $r->headers->set('Content-Type', 'application/atom+xml; charset=UTF-8');
    return $r; 
});

$app->get('/etc/', function() { 

    $xmlWriter = new XMLWriter();
    $xmlWriter->openUri('php://output');
    $xmlWriter->setIndent(true);
    include_once("ATOMWriter.php");
    $f = new ATOMWriter($xmlWriter, true);
    $f->startFeed('urn:restnag')
        ->writeStartIndex(1)
        ->writeItemsPerPage(10)
        ->writeTotalResults(1)
        ->writeTitle('/etc/');

    $f->startEntry("urn:restnag:etc")
        ->writeTitle("/etc/nagios3/")
        ->writeLink($GLOBALS['baseurl']."/etc/nagios3/", 'application/atom+xml')
        ->endEntry();
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
