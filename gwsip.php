<?php

function get_sip_code($name, $ip) {
    $rv = "";
    $rv .= "[sip-$name]\n";
    $rv .= "context=rly\n";
    $rv .= "qualify=yes\n";
    $rv .= "type=friend\n";
    $rv .= "insecure=invite\n";
    $rv .= "host=" . $ip . "\n";
    $rv .= "\n";
    
    return $rv;
}


function get_gw_sip_helper($arr) {
    global $gateways, $registrars, $parse_tree;
    foreach($arr as $name => $val) {
        /* $k is the regis name */
        $filename = "${name}-" . $val['ip'] . "-sip.conf";
        $fout = fopen($filename, "w");
        $rv = "";
        
        foreach(['pri', 'sip', 'fxo'] as $type) {
            if (isset($gateways[$type])) {
                foreach($gateways[$type] as $k => $v) {
                    if ($name != $k) {
                        $rv .= get_sip_code($k, $v['ip']);
                    }
                }
            }
        }
        
        foreach ($registrars as $n => $r) {
            $rv .= get_sip_code($n, $r['ip']);
            /* Check for backup registrar */
            if($parse_tree['bregs'][$n]['ip_backup'] != "-1") {
                /* Backup exists */
                $rv .= get_sip_code("$n-backup", $parse_tree['bregs'][$n]['ip_backup']);
            } 
        }        
        fwrite($fout, $rv);
        fclose($fout);
    }
}    

function gen_gw_sip()
{
    global $gateways;
    $rv = "";
    $arr = array('pri', 'sip');
    foreach ($arr as $n => $gwtype) {
        if(isset($gateways[$gwtype])) {
            get_gw_sip_helper($gateways[$gwtype]);
        }
    }
}


    
?>
