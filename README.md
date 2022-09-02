# AstConf
Asterisk Configuration Generator

## How to use AstConf
AstConf is a php program that reads in an input description of an
exchange powered by Asterisk.  It generates Asterisk sip.conf and
extensions.conf file that can be directly used on the VoIP server.

AstConf generates the configuration in line with the Indian Railways
practice for configuring an exchange. 

The following command is used to invoke AstConf.
> $ php AstParser.php < input_file >

AstParser.php contains the main program for AstConf. The input file is
the exchnage configuration file. A sample file is in the __test__
folder as __ex1.ast__.



