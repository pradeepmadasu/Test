<?
//=================================================================================================================
// report-starmaker200.php - generate starmaker bonus data for 200PV
//
// Copyright (c) 2007-2014 by memberTEK, LLC
//=================================================================================================================

	require_once('../config/config.php');
	require_once('../shared/database.php');
	require_once('../shared/lib.php');

	$Year = intval($argv[1]);
	$Month = intval($argv[2]);

	print "Report-Starmaker <br /> Year: $Year  ## Month: $Month <br />\n";
	$File = fopen('detail/detail-starmaker300.csv', 'w');
	fputcsv($File, array('Starmaker', 'Star/Downline', 'PV'));


	$Data = array();
// Made changes to modify PV to 300
	$Results = DatabaseQuery("SELECT MemberId, PV FROM Volume WHERE Year = $Year AND Month = $Month AND PV >= 300");
	$Count = 0;
	
	
	while ($Row = DatabaseFetchAssoc($Results))
	{
// Made changes to modify PV to 300
		$Results2 = DatabaseQuery("SELECT Members.MemberId, PV FROM Members INNER JOIN Volume ON Members.MemberId = Volume.MemberId " .
			"WHERE Year = $Year AND Month = $Month AND PV >= 300 AND SponsorMemberId = {$Row['MemberId']} AND Type = 'D' ");
		$DownlineCount = DatabaseNumRows($Results2);
		if ($DownlineCount >= 3)
		{
			$DownlineMembers = array();
			while ($Row2 = DatabaseFetchAssoc($Results2)) { $DownlineMembers[] = array('MemberId' => FixId($Row2['MemberId']), 'PV' => $Row2['PV']); }		
				
			$Data[] = array('Star' => FixId($Row['MemberId']), 'PV' => $Row['PV'], 
				'StarMaker' => FixId(DatabaseGet('Members', 'SponsorMemberId', "MemberId = {$Row['MemberId']}", 0)), 'Downline' => $DownlineMembers);
				
		}
		$Count++;
	}

	$PreviousStars = array(
'509572',
'504137',
'507203',
'502167',
'504504',
'502760',
'505432',
'502771',
'503144',
'502165',
'507117',
'505911',
'502558',
'509412',
'502288',
'506249',
'502271',
'505322',
'506194',
'509084',
'502158',
'505299',
'504734',
'502270',
'508080',
'502379',
'506638',
'502567',
'502186',
'505481',
'505912',
'504649',
'503188',
'506218',
'502324',
'509646',
'509084',
'505481',
'506951',
'508582',
'502558',
'502271',
'502162',
'502162',
'509822',
'508295',
'510361',
'510506',
'502568',
'509668',
'508310',
'502155',
'509291',
'502326',
'507878',
'502185',
'510816',
'502849',
'509005',
'502163',
'510743',
'502154',
'510362',
'509289',
'505822',
'505761',
'511055',
'511066',
'511275'
);

	foreach($Data as $Datum)
	{
		if (! in_array($Datum['Star'], $PreviousStars))
		{
			print "{$Datum['StarMaker']}\t{$Datum['Star']}\r\n";

			if ($Datum['StarMaker'] != 500000)
			{
				fputcsv($File, array($Datum['StarMaker'], $Datum['Star']));
				foreach($Datum['Downline'] as $Downline)
				{
					fputcsv($File, array('',$Downline['MemberId'], $Downline['PV']));
				}
			}
		}
		else
		{
			if ($Datum['StarMaker'] != 500000) { fputcsv($File, array($Datum['StarMaker'], $Datum['Star'], 'Paid Previously')); }
		}
	}

	function FixId($MemberId) { return $MemberId + 500000; }

	fclose($File);

?>