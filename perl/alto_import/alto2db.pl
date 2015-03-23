#!/usr/bin/perl -w

###############################################################################
#
# This script puts text files into a database that is to be used for the
# Dictionary Attestation Tool.
#
# Typical way of calling:
#
# > alto2db.pl -h [HOST] -d [DB_NAME] -u [USERNAME] -p [PASSWORD] -i [INPUT DIR]
#
###############################################################################

use strict;

use DBI;
use Encode;
use Time::HiRes;
use Getopt::Std;

my $fTime = Time::HiRes::time();

my %hOptions=();
getopts("h:d:u:p:i:",\%hOptions);

unless( $hOptions{h} && $hOptions{d} && $hOptions{u} && $hOptions{p} && $hOptions{i} ) {
  die "\n alto2db.pl -h HOST -d DATABASE -u USERNAME -p PASSWORD -i INPUT_DIR\n\n";
}

# The database parameters
my $sDatabase = $hOptions{d};
my $sHost = $hOptions{h};
my $sUser = $hOptions{u};
my $sPassword = $hOptions{p};
my $sInputDir = $hOptions{i};

my $dbh = DBI->connect("dbi:mysql:$sDatabase:$sHost:3306", $sUser, $sPassword,
		       {RaiseError => 1});
$dbh->{'mysql_enable_utf8'} = 1;
$dbh->do('SET NAMES utf8');

# try to open the directory with the files
opendir (DIR, $sInputDir) or die "\n Couldn't open the source directory (-i argument)\n\n";

# build a list of the xml files in that directory
my $myFiles = "";
my $myFileSeparator = "";
if ($sInputDir !~ m/\//)
	{
	$sInputDir .= "/"; # add final slash if it's missing
	}
while (my $file = readdir(DIR)) {

	# Use a regular expression to ignore files beginning with a period
	if ($file =~ m/\.xml$/)
		{
		$myFiles .= $myFileSeparator.$sInputDir.$file;
		$myFileSeparator = ",";
		}
}

closedir(DIR);

unless ( $myFiles ne "") {
  die "\n The source directory contains no xml files \n\n";
}
# get files to process into an array
my @xmlFiles = split(',', $myFiles);


# For printing if necessary
binmode(STDOUT, ":encoding(utf8)");

# This chuck length sets the amount of characters you will see in a screen
# in the Attestation Tool.
# The 'lemmata' (=file names) will be postfixed with a number to keep them
# apart.
# Set to undef if you don't want chunks.
my $iChunkLength = 10000;
undef $iChunkLength;
# This hash contains the tags as found in the texts (which are treated as
# regular expressions, see below).
my %hTags = (PERS => "<NE_PER>",
	     LOC => "<NE_LOC>",
		 'NOT KNOWN' => "<NE_UNK>",
	     ORG => "<NE_ORG>");
my %hTypes = %{swapHash(\%hTags)};
# This regular expression describes all end tags
my $reEndTag = qr!(</NE>)(.*)$!i;
my @aGroupAttestations;

# Get the type id's from the database
my %hTypeIds = %{getTypeIds()};


# The command line arguments are file names
for my $i( 0 .. $#xmlFiles ) {

print "$xmlFiles[$i]";

  my $sText;
  @aGroupAttestations = (); # Empty it again
  open(FH, "<:encoding(utf8)", "$xmlFiles[$i]")
    or die ("Couldn't open file $_ for reading: $!\n");

  # NOTE that we (mis)use the file name as 'lemma' of the headword
  # We take the path part off because doesn't really look good in the tool
  # and actually doesn't add much anyway.
  my $sLemma = $xmlFiles[$i];
  $sLemma =~ s#^.*[\/]([^\/]+)$#$1#;
  print "$sLemma\n";

  my $iFilePart = 1;
  # We go through the text by chunk (a piece of text of $iChunkLength
  # characters).
  # Every chunk gets its own 'lemma' in the database, so it is presented in the
  # tool in one screen. This makes life easier for the users (because it
  # doesn't take ages to render the page every time).
  while( (my $sText = getChunk()) ) {
    # If there is no text that makes sense, we skip this part
    # NOTE that the /S is used because tokenizing is /s based
    next unless( $sText =~ /\S/ );

    # First put the lemma in
    my $iLemmaId = insertLemma("${sLemma}_$iFilePart");
    # Here we put in an empty quotations only to get an id
    my $iQuotationId = insertQuotation($iLemmaId, \$sText); 
	# TODO: store text along with file name (so it can be exported with same name)

    # To make matching later on easier we put a space at the end
    $sText .= " ";
    handleText(\$sText, $iLemmaId, $iQuotationId); 
    # we now have the complete alto text in one quotation (only one page)
    $iFilePart++;
  }
  close(FH);
}

$dbh->disconnect();

printTime($fTime);

### SUBS ######################################################################

# NOTE: In Perl all filehandles are global (so FH is the one opened earlier on)
sub getChunk {
  my $sText = '';
  while( my $sLine = <FH> ) {
    $sText .= $sLine;
    # In the material at hand, sentences end in a period with white spaces
    # around it, and NOT a single letter before (as in "F . van Lelyveld")
    if( $sLine =~ /(\S*)\s\.\s*$/ ) {
      # Chunks are per sentence. If the length is more than iChunkLength
      # characters, we got a chunk.
      last if (defined($iChunkLength) && (length($sText) > $iChunkLength) &&
	      (! $1 || length($1) > 1) );
    }
  }
  return ( length($sText) ) ? $sText : undef;
}

sub getAttribute
{
  my ($tag,$attname) = @_;
  if ($tag =~ /$attname=('([^']*)'|"([^"]*))/)
  {
    my $v1 = $2;
    my $v2 = $3;
    if (defined($v1)) { return $v1; };
    return $v2;
  }
}
#  <String, CONTENT, ID
sub handleText {
  my ($srText, $iLemmaId, $iQuotationId) = @_;

  my $iOnset = 0;
  my ($sTag, $iOffset, $sWordForm, $sTokenizedQuotation);
  my $sCurrentTag = 0;
  my $iPos = 0;
  pos($$srText) = 0; # 
  # This is the tokenizing
  while( $$srText =~ /<String[^<>]*>/gs ) 
  {
    my $tag = $&;
    $iOffset = pos($$srText); $iOnset = $iOffset - length($&);

    $sWordForm = getAttribute($tag,"CONTENT");
    $sCurrentTag = getAttribute($tag,"ALTERNATIVE");
    if (!$sCurrentTag || $sCurrentTag =~ /B-/)
    {
	   # insert attestation group into the database
       insertAttestationGroup($iQuotationId);
    }
    my $sTokenId =  getAttribute($tag,"ID");
    
    # NOTE: the onset is 0-based index of the string
    # The offset is 1-based index of the last character in the string
    # So a text starting with "Daar ..." results is 'Daar, 0, 4'
    if($sWordForm !~ /^\s*$/s) {
      handleToken($iQuotationId, \$iPos, $iOnset, $iOffset, $sWordForm,
		  \$sCurrentTag, \$sTokenizedQuotation, $sTokenId);
    }
    $iOnset = pos($$srText);
  }
  # NOTE: There is always one newline too many and the tool
  # expects fields not to end in a newline. So we chomp it off.
  if( $sTokenizedQuotation ) { 
    chomp($sTokenizedQuotation);
    updateQuotation($iQuotationId, \$sTokenizedQuotation);
  }
}

sub handleToken {
  my ($iQuotationId, $irPos, $iOnset, $iOffset, $sWordForm, $srCurrentTag,
      $srTokenizedQuotation, $sTokenId) = @_;

  # See if there is a start tag (this is where the start tags are used as
  # regular expressions, as noted above).
    addToken($iQuotationId, $irPos, $sWordForm, $iOnset, $iOffset,
	     $srCurrentTag, $srTokenizedQuotation, $sTokenId);
}

sub addToken 
{
  my ($iQuotationId, $irPos, $sWordForm, $iOnset, $iOffset, $srCurrentTag,
      $srTokenizedQuotation, $sTokenId) = @_;

  my ($sCanonForm, $iNewOnset, $iNewOffset) =
    toCanonicalForm($sWordForm, $iOnset, $iOffset);
  # We are in a tag
  if( $$srCurrentTag ) {
    # NOTE that we use the reliability score here. We set it to 0 which causes
    # the quotations to be displayed in green in the tool (which is easier
    # for reading some users found).
    # It doesn't really matter as the reliability doesn't really mean anything
    # in the case.
    my $type = $$srCurrentTag;
    $type =~ s/.*-//; # hm
    my $typeId = $hTypeIds{$type};
    if (!$typeId)
    {
      die "Undefined NE type '$type'. Please predefine this type in the 'types' table in the database!\n";
    }

    $dbh->do("INSERT INTO attestations " .
	     "(quotationId, onset, offset, wordform, typeId, reliability, tokenId) " .
	     "VALUES (?, ?, ?, ?, ?, 0, ?)", undef,
	     $iQuotationId, $iOnset, $iOffset, $sWordForm,
	     $typeId, $dbh->quote($sTokenId));
    push(@aGroupAttestations, [$iOnset, $$irPos, $sWordForm]);
  }

  $$srTokenizedQuotation .=
    "$iNewOnset\t$iNewOffset\t$sCanonForm\t$sWordForm\n";
  $$irPos++; # One up
  return $iNewOffset; # We sometimes need this
}


# Canonical form is:
# - lowercase
# - no <TAGS>
# - no comma's (,), dots (.) or semi-colons (;)
sub toCanonicalForm {
  my ($sString, $iOnset, $iOffset) = @_;

  $sString = lc($sString);
  my $sRegExpNonCanonicals = "((<[^>]+>|[\.\,;])+)";
  if( $sString =~ /^$sRegExpNonCanonicals(.+)$/ ) {
    $sString = $2;
    $iOnset += length($1);
  }
  if( $sString =~ /^(.+)$sRegExpNonCanonicals$/ ) {
    $sString = $1;
    $iOffset -= length($2);
  }
  return ($sString, $iOnset, $iOffset);
}

sub swapHash {
  my ($hrHash) = @_;

  my %hResult;
  while( my ($sKey, $sValue) = each(%$hrHash) ) {
    $hResult{$sValue} = $sKey;
  }
  return \%hResult;
}

### Database functions ########################################################

sub getTypeIds {
  my %hResult;

  my $qhQuery = $dbh->prepare("SELECT id, name FROM types");
  $qhQuery->execute();

  while (my $hrRow = $qhQuery->fetchrow_hashref()) {
    $hResult{$hrRow->{name}} = $hrRow->{id};
  }
  $qhQuery->finish();

  return \%hResult;
}

#
sub insertLemma {
  my ($sLemma) = @_;

  my $iLemmaId = undef;
  my $qhQuery =
    $dbh->prepare("SELECT id FROM lemmata WHERE lemma = '$sLemma'");
  $qhQuery->execute();

  if (my $hrRow = $qhQuery->fetchrow_hashref()) {
    $iLemmaId = $hrRow->{id};
  }
  $qhQuery->finish();

  return $iLemmaId if( $iLemmaId );

  $dbh->do("INSERT INTO lemmata (lemma) VALUES (?) " .
	   "ON DUPLICATE KEY UPDATE id = id", undef, $sLemma);
  return $dbh->{'mysql_insertid'};
}


#
sub insertQuotation {
  my ($iLemmaId, $srText) = @_;

  my $iQuotationId = undef;

  my $qhQuery =
    $dbh->prepare("SELECT id FROM quotations WHERE lemmaId = $iLemmaId" .
		  " AND quotation = ". $dbh->quote($$srText));
  $qhQuery->execute();

  if (my $hrRow = $qhQuery->fetchrow_hashref()) {
    $iQuotationId = $hrRow->{id};
  }
  $qhQuery->finish();

  return $iQuotationId if( $iQuotationId );

  $dbh->do("INSERT INTO quotations (lemmaId, quotation) VALUES (?, ?) " .
	   "ON DUPLICATE KEY UPDATE id = id", undef, $iLemmaId, $$srText);
  return $dbh->{'mysql_insertid'};
}

sub updateQuotation {
  my ($iQuotationId, $srTokenizedQuotation) = @_;

 $dbh->do("UPDATE quotations SET tokenizedQuotation = ? " .
	  "WHERE id = $iQuotationId", undef, $$srTokenizedQuotation);
}

sub insertAttestationGroup {
  my ($iQuotationId) = @_;

  # If the last or first is a period or comma, then this is never interesting
  # E.g: "F. Jansen ." of ", Leiden"
  # So we delete that 'token' (the period/comma).
  if( $#aGroupAttestations > 0 ) {
    print $#aGroupAttestations . "\n" if( $#aGroupAttestations > 10);
    if( $aGroupAttestations[0]->[2] =~ /^[\,\.]$/) {
      $dbh->do("DELETE FROM attestations " .
	       "WHERE quotationId = $iQuotationId" .
	       "  AND onset = $aGroupAttestations[0]->[0]");
      shift(@aGroupAttestations);
    }
    if( $aGroupAttestations[$#aGroupAttestations]->[2] =~ /^[\,\.]$/) {
      $dbh->do("DELETE FROM attestations " .
	       "WHERE quotationId = $iQuotationId" .
	       "  AND onset = $aGroupAttestations[$#aGroupAttestations]->[0]");
      pop(@aGroupAttestations);
    }
  }

  # If there is a group left after this
  if( scalar(@aGroupAttestations) > 1) {
    my $iMaxId = undef;
    for( @aGroupAttestations ) {
      # If we already added one in this loop
      if( $iMaxId ) {
	$dbh->do("INSERT INTO groupAttestations (id, attestationId, pos) " .
		 "SELECT $iMaxId, attestations.id, $_->[1] " .
		 "FROM attestations " .
		 "WHERE quotationId = $iQuotationId AND onset = $_->[0]");
      }
      else { # Nothing added in this loop
	# Add one
	$dbh->do("INSERT INTO groupAttestations (id, attestationId, pos) " .
		 "SELECT tmp.groupId, attestations.id, $_->[1] " .
		 "FROM attestations, " .
		 "(SELECT IF(MAX(id) IS NULL, 1, MAX(id)+ 1) AS groupId" .
		 " FROM groupAttestations) tmp" .
		 " WHERE quotationId = $iQuotationId AND onset = $_->[0]");
	# Get the id of the group just created
	my $qhQuery =
	  $dbh->prepare("SELECT groupAttestations.id " .
			"FROM attestations, groupAttestations " .
			"WHERE attestations.quotationId = $iQuotationId" .
			"  AND attestations.onset = $_->[0]" .
			"  AND groupAttestations.attestationId =" .
			" attestations.id"
		       );
	$qhQuery->execute();
	if (my $hrRow = $qhQuery->fetchrow_hashref()) {
	  $iMaxId = $hrRow->{id};
	}
	$qhQuery->finish();
      }
    }
  }
  @aGroupAttestations = (); # Empty the array again
}

sub printTime {
  my ($fTime) = @_;

  my $fTimeDiff = Time::HiRes::time() - $fTime;

  # 3670.06 seconds becomes 61 minutes
  my $iMinutes = sprintf("%.0f", int($fTimeDiff / 60));
  # And 10.06 seconds
  my $iSeconds = ($fTimeDiff % 60);
  # Which is 1 hour
  my $iHours = sprintf("%.0f", ($iMinutes / 60));
  # And 1 minute
  $iMinutes %= 60;

  print "It took: $fTimeDiff seconds\n";
  if ($iMinutes  > 0) 
  {
    print "Which is ";
    print "$iHours hours and " if( $iHours);
    print "$iMinutes minutes and $iSeconds second";
    print "s" if($iSeconds != 0);
    print ".\n";
  }
}
