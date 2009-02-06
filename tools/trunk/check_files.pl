#!/usr/bin/perl

# $Id$

foreach my $filename (@ARGV)
{
  local $/ = undef;

  if (! -f $filename)
  {
    print "Skipping non-regular file $filename\n";
  }
  elsif (-B $filename)
  {
    print "Skipping binary file $filename\n";
  }
  else
  {
    open FILE,'<',$filename or die "Failed to open $filename\n";

    my $text = <FILE>;
  
    close FILE;
  
    if ($text !~ m/\n$/)
    {
      print "$filename missing final \\n\n";
    }
    elsif ($text =~ m/\r/)
    {
      print "$filename comtains CR(s)\n";
    }
  }
}
