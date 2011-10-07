<?php

function is_nagiosgrapher_running() {
    $pidfile = '/var/run/nagiosgrapher/nagiosgrapher.pid';
    if (!file_exists($pidfile)) {
        return false;
    } else {
        $pid = trim(file_get_contents($pidfile));
        exec('ps aux | grep '.$pid.' | grep ^nagios', $o, $r);
        return $r == 0;
    }
}
