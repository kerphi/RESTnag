<?php

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
    if ($lnum == -1) {
        $cfg[] = $key.'='.$newvalue;
        $patched = true;
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

