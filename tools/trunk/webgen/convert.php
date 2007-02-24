#!/usr/bin/php

<?php
# get arguments =>  template, input file, output file and title

$bin_dir = $_SERVER['argv'][0];
$bin_dir = preg_replace('/\/[^\/]*$/','',$bin_dir);

if (isset($_SERVER['argv'][1]))
{
  $src_dir = $_SERVER['argv'][1];
}
else
{
  $src_dir = '/home/jberanek/mrbs/mrbs_cvs/branch-1.2/mrbs';
}

if (isset($_SERVER['argv'][2]))
{
  $web_dir = $_SERVER['argv'][2];
}
else
{
  $web_dir = '.';
}

$templatefile = $bin_dir.'/main.dwt';

# README
$to_process[] = array(
                  "$src_dir/README",
                  "$web_dir/README.html",
                  "README",
                  "MRBS: README"
                );
# INSTALL
$to_process[] = array(
                  "$src_dir/INSTALL",
                  "$web_dir/INSTALL.html",
                  "INSTALL",
                  "MRBS: Installation Instructions"
                );
# AUTHENTICATION
$to_process[] = array(
                  "$src_dir/AUTHENTICATION",
                  "$web_dir/AUTHENTICATION.html",
                  "AUTHENTICATION",
                  "MRBS: Authentication support"
                );
# NEWS
$to_process[] = array(
                  "$src_dir/NEWS",
                  "$web_dir/NEWS.html",
                  "NEWS",
                  "MRBS: NEWS"
                );
# NEWS
$to_process[] = array(
                  "$src_dir/ChangeLog",
                  "$web_dir/ChangeLog.html",
                  "ChangeLog",
                  "MRBS: ChangeLog"
                );
# LANGUAGE
$to_process[] = array(
                  "$src_dir/LANGUAGE",
                  "$web_dir/LANGUAGE.html",
                  "LANGUAGE",
                  "MRBS: Language Support"
                );


foreach( $to_process as $entry )
{
    print "processing " .$entry[0] ."...\n";
    # read in input file
    $file = $entry[0];
    if( $handle = fopen($file, "r") )
    {
        $input = fread($handle, filesize($file));
        fclose($handle);
    }
    else
    {
        print "ERROR: Can't read $file\n";
        continue;
    }
    
    # escape all html
    $escaped_input = htmlspecialchars($input, ENT_QUOTES);
    
    # turn URLS into links
    # skip INSTALL as links used as examples would be double expanded.
    if( basename($file) != "INSTALL" )
    {
        $escaped_input = ereg_replace("[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]",
                                     "<a href=\"\\0\">\\0</a>",
                                     $escaped_input
                                    );
    }
    
    # needed to highlight version numbers in NEWS
    $final_input = preg_replace('/(Version .*?) \(/', '<strong>$1</strong> (', $escaped_input);
    
    # read in template
    $file = $templatefile;
    if( $handle = fopen($file, "r") )
    {
        $template = fread($handle, filesize($file));
        fclose($handle);
    }
    else
    {
        print "ERROR: Can't read $file\n";
        continue;
    }
    
    
    $search = array ( "/<!-- insert page title -->/",
                      "/<!-- insert title -->/",
                      "/<!-- insert body -->/"
                    );
    $replace = array ( $entry[2],
                       $entry[3],
                       "<pre>$final_input</pre>"
                     );
    $text = preg_replace($search, $replace, $template);
    
    # write output file
    $file = $entry[1];
    if( $handle = fopen($file, "w") )
    {
        fwrite($handle, $text);
        fclose($handle);
    }
    else
    {
        print "ERROR: Can't write to $file\n";
        continue;
    }
}
?>
