<?php

function gen_akuvox_dir() {
    global $registrars;

    $f = fopen("akuvox-dir.xml", "w") or die ("Cannot open akuvox-dir.xml!");
    fwrite($f, "<?xml version=\"1.0\" encoding=\"utf-8\" ?>\n");
    fwrite($f, "<Directory Name=\"ECRHQ\">\n");
    
    $i = 1;
    foreach ($registrars as $rname => $regis) {
        foreach($regis['icoms'] as $iname => $icom) {
			foreach($icom['phones'] as $name => $ph) {
                $name = $ph['disp_name'];
                $rly_no = $ph['unique_no'];
                $dir_str = "<Contact Id=$i Name=\"$name\" Office=\"$rly_no\" />\n";
                fwrite($f, $dir_str);
                $i++;
            }
        }
    }
    fwrite($f, "</Directory>\n");
    fclose($f);
}

function generate_dir()
{
    gen_akuvox_dir();
}

?>
