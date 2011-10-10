<?php

require_once __DIR__.'/lib/nagiosgrapher.inc.php';

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

$app->get('/etc/nagiosgrapher/ngraph.d/{confname}.ncfg', function($confname) {
    $cfgpath = '/etc/nagiosgrapher/ngraph.d/'.$confname.'.ncfg';
    if (file_exists($cfgpath)) {
        $output = file_get_contents($cfgpath);
        $r = new Response($output, 200);
        $r->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        return $r;
    } else {
        return new Response('', 404);
    }
});

$app->put('/etc/nagiosgrapher/ngraph.d/{confname}.ncfg', function($confname) use ($app) {
    $request = $app['request'];
    $config  = $request->getContent();
    $conf_file_path = '/etc/nagiosgrapher/ngraph.d/'.$confname.'.ncfg';
    $is_config_new = !file_exists($conf_file_path);

    // check disk permission
    if ($is_config_new) {
        if (!is_writable(dirname($conf_file_path))) {
            return new Response("nagiosgrapher config dir is not writable", 403);
        }
    } else {
        if (!is_writable($conf_file_path)) {
            return new Response("nagiosgrapher config file is not writable", 403);
        }
    }

    // create a backup from the old config
    $backup = !$is_config_new ? file_get_contents($conf_file_path) : '';

    // is nagiosgrapher running ?
    $is_running = is_nagiosgrapher_running();
    if (!$is_running) {
        exec('sudo /etc/init.d/nagiosgrapher start', $o, $r);
        if (!is_nagiosgrapher_running()) {
            return new Response("nagiosgrapher can't be started", 500);
        }
    }
    
    // test the new config
    file_put_contents($conf_file_path, $config);
    exec('sudo /etc/init.d/nagiosgrapher restart', $o, $r);
    if (is_nagiosgrapher_running()) {
        $status = array(implode("\n", $o), 200); // Success
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
        exec('sudo /etc/init.d/nagiosgrapher restart', $o, $r);
        if (!is_nagiosgrapher_running()) {
            return new Response("nagiosgrapher config can't be restored", 500);
        }
    } else {
        // everything is ok so just restart nagios3 to take into account new graphs
        exec('sudo /etc/init.d/nagios3 reload', $o, $r);
    }

    // restore nagiosgrapher status (stop it if necessary)
    if (!$is_running) {
        exec('sudo /etc/init.d/nagiosgrapher stop', $o, $r);
    }

    $r = new Response($status[0], $status[1]);
    $r->headers->set('Content-Type', 'text/plain; charset=UTF-8');
    return $r;
});

$app->get('/etc/nagiosgrapher/', function() {
    $r = new Response('', 302);
    $r->headers->set('Location', $GLOBALS['baseurl'].'/etc/nagiosgrapher/ngraph.d/');
    return $r; 
});

