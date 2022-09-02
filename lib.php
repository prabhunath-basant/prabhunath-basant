<?php

function get_current_line_no()
{
    global $line_no;
    return $line_no;
}

function get_phone_info($phone_name) 
{
	 global $registrars;
	 $rv = "";
	 foreach ($registrars as $regis => $reg) {
		 foreach ($reg['icoms'] as $icom_name => $icom) {
			 foreach ($icom['phones'] as $ph => $ph_val) {
				 if ($ph == $phone_name) {
					 $rv = [$ph_val['disp_name'], $ph_val['unique_no'], $ph_val['secret'], $ph_val['pstn'], $ph_val['icom_no'], $icom_name, $regis];
					 return $rv;
				 }
			 }
		 }
	 }
}

function get_regis_backup($regis)
{
    global $registrars;
    //print "****************$regis \n";
    //print_r($registrars);
    return $registrars[$regis]['ip_backup'];
}

?>
