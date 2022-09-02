<?php

function gen_reg_backup_conf() 
{
    global $registrars;
    foreach($registrars as $regis => $rv) {
        if ($rv['ip_backup'] != "-1") {
            /* generate the backup configuration files */
            $main_ip = $rv['ip'];
            $backup_ip = $rv['ip_backup'];
            $main_sip_file = "$regis-$main_ip-sip.conf";
            $backup_sip_file = "$regis-$backup_ip-sip.conf";
            $cmd = "cp $main_sip_file $backup_sip_file";
            system($cmd, $retval);
            $main_exten_file = "$regis-$main_ip-extensions.conf";
            $backup_exten_file = "$regis-$backup_ip-extensions.conf";
            $cmd = "cp $main_exten_file $backup_exten_file";
            system($cmd, $retval);
        }
    }
}


?>