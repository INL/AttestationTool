#!/usr/bin/perl -w

use strict;
use Getopt::Std;
use DBI;

our ($opt_d, $opt_h, $opt_u, $opt_p, $opt_D);
getopts('d:h:u:p:D:');

my $sHelpText = <<HELP_TEXT;

  $0 -h hostname  -u username  -p password  -d YYYY-MM-DD  -D database1,database2,... 

  Generate a statistics report about the redactional activities
  in the Dictionary Attestation Tool

  -d
     Date, in format YYYY-MM-DD
  -D
     Database to generate a report about. If more databases must be 
     processed, put their names comma separated.

  -h 
     HOSTNAME
  -u 
     USERNAME
  -p 
     PASSWORD

HELP_TEXT

die $sHelpText unless( $opt_d && $opt_h && $opt_u && $opt_p && $opt_D );

my $sDate = $opt_d;
if( $sDate !~ /^(\d{4}-\d{2}-\d{2})$/i)  {
  die "You must supply a date like YYYY-MM-DD\n.";
}

my $sDbHost = $opt_h;
my $sUsername = $opt_u;
my $sPassword = $opt_p;
my @aDatabases = split(/,/, $opt_D);

my $hrAveragesPerDB;
my %hTotals;
for my $sDatabase ( @aDatabases ) {
  $sDatabase =~ s/^\s+|\s+$//g; # trim
  $hrAveragesPerDB = getResultsForDatabase($sDatabase);

  printAverages($hrAveragesPerDB);
}

# Totals:
print "\n\n----- ";
print "Overall totals:";
print " -----\n\n";
printAverages(\%hTotals);

###

sub getResultsForDatabase {
  my ($sDatabase) = @_;

  my $dbh = DBI->connect("DBI:mysql:database=$sDatabase;host=$sDbHost",
			 $sUsername, $sPassword, {'RaiseError' => 1});


  my ($sWntId, $iNrOfCits, $sName, $iRevDateInSecs, $sRevDate);
  my $sQuery =
    "SELECT  externalLemmaId, count(*) AS nrOfCits, revisors.name, " .
      "UNIX_TIMESTAMP(revisionDate) AS revdate, revisionDate " .
	"FROM  lemmata, quotations, revisors " .
	  "WHERE revisionDate < '$sDate' " .
	    "AND quotations.lemmaId = lemmata.id " .
	      "AND revisors.id = revisorId " .
		"GROUP BY lemmata.id " .
		  "ORDER BY revdate DESC";
#    "SELECT externalLemmaId, count(*) AS nrOfCits, revisors.name, ".
#      "UNIX_TIMESTAMP(revisionDate) AS revdate, revisionDate " .
#	"FROM lemmata, revisors where revisionDate < '2009-24-02' AND revisors.id = revisorId GROUP BY externalLemmaId " .
#	  "ORDER BY revdate DESC";

  my %hResult;
  my $qhQuery = $dbh->prepare($sQuery);
  $qhQuery->execute;
  while ( (($sWntId, $iNrOfCits, $sName, $iRevDateInSecs, $sRevDate) =
	   $qhQuery->fetchrow_array()) ) {
    if ( exists($hResult{$sName})) {
      # Periode van een half uur (15min x 60sec) = 900 seconden
      if ( ($hResult{$sName}->{iMaxDate} - $iRevDateInSecs) > 900) {
	$hResult{$sName}->{iMaxPeriod}++;
	$hResult{$sName}->{$hResult{$sName}->{iMaxPeriod}} =
	  {iStart => $iRevDateInSecs,
	   iEnd => $iRevDateInSecs,
	   sStartDate => $sRevDate,
	   sEndDate => $sRevDate,
	   iNrOfCits => $iNrOfCits,
	   iNrOfLemmata => 1,
	  };
      }
      else {
	$hResult{$sName}->{$hResult{$sName}->{iMaxPeriod}}->{iStart} =
	  $iRevDateInSecs;
	$hResult{$sName}->{$hResult{$sName}->{iMaxPeriod}}->{sStartDate} =
	  $sRevDate;
	$hResult{$sName}->{$hResult{$sName}->{iMaxPeriod}}->{iNrOfCits} +=
	  $iNrOfCits;
	$hResult{$sName}->{$hResult{$sName}->{iMaxPeriod}}->{iNrOfLemmata}++;
      }
      $hResult{$sName}->{iMaxDate} = $iRevDateInSecs;
    }
    else {			# First time for this revisor
      $hResult{$sName}->{iMaxDate} = $iRevDateInSecs;
      $hResult{$sName}->{iMaxPeriod} = 1;
      $hResult{$sName}->{$hResult{$sName}->{iMaxPeriod}} =
	{iStart =>  $iRevDateInSecs,
	 iEnd => $iRevDateInSecs,
	 sStartDate => $sRevDate,
	 sEndDate => $sRevDate,
	 iNrOfCits => $iNrOfCits,
	 iNrOfLemmata => 1,
	};
    }
  }
  $qhQuery->finish;
  $dbh->disconnect();

  print "\n\n----- ";
  print "Database: $sDatabase";
  print " -----\n\n";

  return getAverages(\%hResult);
}

sub printAverages {
  my ($hrAverages) = @_;

  my ($iTotalNrOfLemmata, $iTotalNrOfCits) = (0,0);

  for my $sName (keys(%$hrAverages)) {
    printRevisorAverages($sName, $hrAverages->{$sName});
    $iTotalNrOfLemmata += $hrAverages->{$sName}->{iNrOfLemmata};
    $iTotalNrOfCits += $hrAverages->{$sName}->{iNrOfCits};
  }
  print "Total: $iTotalNrOfLemmata lemmata, $iTotalNrOfCits quotes\n";
}

sub printRevisorAverages {
  my ($sName, $hrRevisorAverages) = @_;

  my $iTimeDiff = $hrRevisorAverages->{iNrOfSecs};

  return if ($iTimeDiff == 0);

  # 3670.06 seconds becomes 61 minutes
  my $iMinutes = sprintf("%.0f", ($iTimeDiff / 60));
  # And 10.06 seconds
  my $iSeconds = ($iTimeDiff % 60);
  # Which is 1 hour
  my $iHours = sprintf("%.0f", ($iMinutes / 60));
  # And 1 minute
  $iMinutes %= 60;

  print "$sName attested for ";
  if( $iMinutes ) {
    
    if( $iHours) {
      print "$iHours hour";
      print "s" if( $iHours > 1);
      print ", ";
    }
    print "$iMinutes minute";
    print "s" if ($iMinutes > 1);
    print " and $iSeconds second";
    print "s" if( $iSeconds > 1);
    print ".\n";
  }
  print "$hrRevisorAverages->{iNrOfLemmata} lemmata (";
  printf("%.2f", ($hrRevisorAverages->{iNrOfLemmata} / $iTimeDiff) * 60);
  print " per minute), ";
  print "$hrRevisorAverages->{iNrOfCits} quotes (";
  printf("%.2f", ($hrRevisorAverages->{iNrOfCits} / $iTimeDiff) * 60);
  print " per minute).\n\n";
}

sub getAverages {
  my ($hrResult) = @_;

  my %hAverages;
  my $hrData;
  for my $sName (keys(%$hrResult)) {
    for my $iPeriod ( 1 .. $hrResult->{$sName}->{iMaxPeriod} ) {
      $hrData = $hrResult->{$sName}->{$iPeriod};
      # Quick and dirty, so as to get subresults
      $hAverages{$sName}->{iNrOfLemmata} += $hrData->{iNrOfLemmata};
      $hAverages{$sName}->{iNrOfCits} += $hrData->{iNrOfCits};
      $hAverages{$sName}->{iNrOfSecs} += ($hrData->{iEnd} - $hrData->{iStart});

      # For globals where everything is kept
      $hTotals{$sName}->{iNrOfLemmata} += $hrData->{iNrOfLemmata};
      $hTotals{$sName}->{iNrOfCits} += $hrData->{iNrOfCits};
      $hTotals{$sName}->{iNrOfSecs} += ($hrData->{iEnd} - $hrData->{iStart});
    }
  }
  return \%hAverages;
}

sub printResult{
  my ($hrResult) = @_;

  my ($sName, $hrData, $iPeriod);
  for $sName (keys(%$hrResult)) {
    print "$sName:\n";
    for $iPeriod ( 1 .. $hrResult->{$sName}->{iMaxPeriod} ) {
      $hrData = $hrResult->{$sName}->{$iPeriod};
      print "Start: $hrData->{sStartDate}\n";
      print "End: $hrData->{sEndDate}\n";
      print "Nr of lemmata: $hrData->{iNrOfLemmata}\n";
      print "Nr of cits: $hrData->{iNrOfCits}\n\n";
    }
  }
}
