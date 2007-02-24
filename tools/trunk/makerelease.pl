#!/usr/bin/perl

use warnings;
use strict;

my $rel_ver = shift;
my $user = shift || 'jberanek';

my $tag = $rel_ver;

$tag =~ s/\./_/g;

$tag = 'mrbs-'.$tag;

if (-d 'mrbs-'.$rel_ver)
{
  (system('rm','-fr','mrbs-'.$rel_ver) == 0) or die "Failed to clean old export\n";
}

(system('cvs','-d',':ext:'.$user.'@mrbs.cvs.sourceforge.net:/cvsroot/mrbs',
       'export','-r',$tag,'-d','mrbs-'.$rel_ver,'mrbs') == 0) or die "Failed to export from CVS\n";

(system('tar','zcvf','mrbs-'.$rel_ver.'.tar.gz','mrbs-'.$rel_ver) == 0) or die "Failed to tar\n";

(system('zip','-rl','mrbs-'.$rel_ver.'.zip','mrbs-'.$rel_ver) == 0) or die "Failed to zip\n";

(system('zip','-d','mrbs-'.$rel_ver.'.zip','mrbs-'.$rel_ver.'/web/new.gif') == 0) or die "Failed to delete from zip\n";

(system('zip','-u','mrbs-'.$rel_ver.'.zip','mrbs-'.$rel_ver.'/web/new.gif') == 0) or die "Failed to update zip\n";
