<?
//=================================================================================================================
// comp-engine.php - Compensation engine
//
// Copyright (c) 2007-2014 by memberTEK, LLC
//
// Must be executed on linux command line.  Will not run in browser.
//
// Command line:
//
// php comp-engine.php YYY MM ID
//
//  where
//
// YYYY 		is year for which commission are to be calculated
//
// MM			is month for which commission are to be calcualted
//
// ID			is commission id
// Function 
//
//=================================================================================================================

	require_once('../config/config.php');
	require_once('../shared/database.php');
	require_once('../shared/lib.php');
	require_once('../shared/datetime.php');
	require_once('../shared/member.php');

// Static compensation plan settings
	
	$OldRankNames = array('Pro Rep', '2400', '4800', '7200', 'Bronze Executive', 'Silver Executive', 'Gold Executive', 
		'Ruby Executive', 'Emerald Executive', 'Diamond Executive');

	$Rank7200 = 25;
	$RankBronze = 30;
	$RankGold = 40;
	
	$RankIds = array(10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70);
	
	$UnilevelPercentages = array(
		array(10, 10, 11, 12, 12, 12, 12, 12, 12, 12, 12, 12, 12),
		array(00, 03, 04, 04, 05, 06, 07, 07, 07, 07, 07, 07, 07),	
		array(00, 00, 00, 02, 03, 03, 03, 03, 03, 03, 03, 03, 03),
		array(00, 00, 00, 00, 00, 03, 03, 03, 03, 03, 03, 03, 03),
		array(00, 00, 00, 00, 00, 00, 00, 02, 02, 02, 02, 02, 02),
		array(00, 00, 00, 00, 00, 00, 00, 00, 02, 02, 02, 02, 02)
	);
	
	$CheckMatchPercentages = array(
		array(00, 00, 00, 00, 00, 00, 10, 10, 12, 15, 17, 20, 20),
		array(00, 00, 00, 00, 00, 00, 00, 10, 10, 12, 15, 17, 20),
		array(00, 00, 00, 00, 00, 00, 00, 00, 10, 12, 15, 17, 20),
		array(00, 00, 00, 00, 00, 00, 00, 00, 00, 10, 12, 15, 20)
	);
	
	$CheckMatchIndividualCaps = array(0, 0, 0, 0, 0, 0, 1000, 2000, 3500, 5000, 7500, 10000, 15000);

	$LeadershipPoolShares = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2, 3, 4);
	
	$RankAdvancementBonuses = array(0, 0, 0, 250, 350, 500, 1000, 2500, 5000, 10000, 15000, 25000, 50000);

// Make sure executed on command line

//	if (! IsCLI()) { exit(); }	

// Year/month must be on command line
	
	$Year = intval($argv[1]);
	$Month = intval($argv[2]);
	
	if ((! $Year) || (! $Month)) { print "ERROR: Year/Month is missing\n"; exit(); }
	
	$NextYear = $Year; $NextMonth = $Month + 1; if ($NextMonth > 12 ) { $NextMonth = 1; $NextYear++; }

	$Month = sprintf("%02d", $Month); $NextMonth = sprintf("%02d", $NextMonth);

	$CommissionId = intval($argv[3]);

	print "Starting compensation engine calculation for $Month/$Year<br />\n";

	DatabaseQuery("DELETE FROM CommissionItems WHERE CommissionId = $CommissionId");

// Determine company-wide total CV

	$Results = DatabaseQuery("SELECT SUM(PV) As TotalCV FROM Volume WHERE Year = $Year AND Month = $Month");
	$Row = DatabaseFetchAssoc($Results);
	$CompanyCV = floatval($Row['TotalCV']);

	print 'Total Company CV: ' . FormatCurrency($CompanyCV, '', ',') . "<br />\n"; 

//-----------------------------------------------------------------------------------------------------------------
// Unilevel Team Commissions
//-----------------------------------------------------------------------------------------------------------------

	print "Starting Unilevel Commissions<br />\n";

	$Vol = array();
	$Results = DatabaseQuery("SELECT * FROM Volume WHERE Year = $Year AND Month = $Month");
	while ($Row = DatabaseFetchAssoc($Results)) { $Vol[$Row['MemberId']] = $Row; }

	exec("php report-team.php $Year $Month $NextYear $NextMonth > tmp/teamcommissions.txt");

	$SubTypes = array();
	$Results = DatabaseQuery("SELECT MemberId, SubType FROM Members");
	while ($Row = DatabaseFetchAssoc($Results)) { $SubTypes[$Row['MemberId']] = $Row['SubType']; }
	

	$File = file('tmp/teamcommissions.txt');
	$Count = 0;
	$Members = array();
	foreach($File as $LineNumber => $Line)
	{
		list($MemberId, $RankName, $L1, $L2, $L3, $L4, $L5, $L6) = explode("\t", trim($Line), 8);
		$MemberId = $MemberId - 500000;
		$RankIndex = array_search($RankName, $OldRankNames);
		if ($RankIndex === false)
		{
			if ($RankName == 'Member') 
			{
				$RankIndex = 0;
			}
			else
			{
				print "Error finding rank name $RankName for $MemberId\n";
			}
		}
		else
		{
			$SubType = @$SubTypes[$MemberId];
			if ($SubType == 'M')
			{
				$C1 = round($L1 * .10, 2);
				$C2 = round($L2 * .03, 2);
				$Commission = $C1 + $C2;
				
				if ($Commission > 0)
				{
					DatabaseInsert('CommissionItems',
						array('CommissionItemId' => 0,
							'CommissionId' => $CommissionId,
							'Type' => 'U',
							'MemberId' => $MemberId,
						'Amount' => $Commission,
							'Detail' => $L1 . '|' . $L2 . '|0|0|0|0|' .
								'10|3|0|0|0|0|' . $C1 . '|' . $C2 . '|0|0|0|0'
						)
					);	
						
				}
			}
			elseif (($Vol[$MemberId]['PV'] < 200) || ($Vol[$MemberId]['CV1'] < 600))
			{
				$Commission = round($L1 * .10, 2);
				
				if ($Commission > 0)
				{
					DatabaseInsert('CommissionItems',
						array('CommissionItemId' => 0,
							'CommissionId' => $CommissionId,
							'Type' => 'U',
							'MemberId' => $MemberId,
						'Amount' => $Commission,
							'Detail' => $L1 . '|0|0|0|0|0|' .
								'10|0|0|0|0|0|' . $Commission . '0|0|0|0|0'
						)
					);	
						
				}				
			}
			else
			{			
				$C1 = round(($L1 * $UnilevelPercentages[0][$RankIndex]) /100, 2);
				$C2 = round(($L2 * $UnilevelPercentages[1][$RankIndex]) /100, 2);
				$C3 = round(($L3 * $UnilevelPercentages[2][$RankIndex]) /100, 2);
				$C4 = round(($L4 * $UnilevelPercentages[3][$RankIndex]) /100, 2);
				$C5 = round(($L5 * $UnilevelPercentages[4][$RankIndex]) /100, 2);
				$C6 = round(($L6 * $UnilevelPercentages[5][$RankIndex]) /100, 2);
	
				$Commission = $C1 + $C2 + $C3 + $C4 + $C5 + $C6;
				
				DatabaseInsert('CommissionItems',
					array('CommissionItemId' => 0,
						'CommissionId' => $CommissionId,
						'Type' => 'U',
						'MemberId' => $MemberId,
						'Amount' => $Commission,
						'Detail' => "$L1|$L2|$L3|$L4|$L5|$L6|" .
							"{$UnilevelPercentages[0][$RankIndex]}|{$UnilevelPercentages[1][$RankIndex]}|" .
							"{$UnilevelPercentages[2][$RankIndex]}|{$UnilevelPercentages[3][$RankIndex]}|" .
							"{$UnilevelPercentages[4][$RankIndex]}|{$UnilevelPercentages[5][$RankIndex]}|" .
							"$C1|$C2|$C3|$C4|$C5|$C6"
					)
				);	
			}
				
			$Members[$MemberId] = 
				array('RankIndex' => $RankIndex,
					'RankId' => $RankIds[$RankIndex]
			);
		}	
		$Count++;
	}
	
	print "Ending Unilevel Commissions  ($Count commission item(s) generated)<br />\n";



//-----------------------------------------------------------------------------------------------------------------
// Retail Bonus
//-----------------------------------------------------------------------------------------------------------------
	
Retail:	
	print "Starting Retail Bonus<br />\n";
	
	exec("php report-retail.php $Year $Month $NextYear $NextMonth > tmp/retailbonus.txt");
		
	$Bonus = array();
	
	$File = file('tmp/retailbonus.txt');
	$Count = 0;
	
	foreach($File as $LineNumber => $Line)
	{
		list($MemberId, $Amount) = explode("\t", $Line, 2);	
	 	$MemberId = $MemberId - 500000;
		if (isset($Bonus[$MemberId]))
		{
			$Bonus[$MemberId] += $Amount;
		}
		else
		{
			$Bonus[$MemberId] = $Amount;
		}
	}
	
	foreach ($Bonus as $MemberId => $Amount)
	{
$SubType = @$SubTypes[$MemberId];
if ($SubType != 'M')
{
		DatabaseInsert('CommissionItems',
				array('CommissionItemId' => 0,
					'CommissionId' => $CommissionId,
					'Type' => 'R',
					'MemberId' => $MemberId,
					'Amount' => $Amount
				)
		);
		$Count++;
}
	}
	
	print "Ending Retail Bonus ($Count commission item(s) generated)<br />\n";
	
//-----------------------------------------------------------------------------------------------------------------
// Starmaker Bonus
//-----------------------------------------------------------------------------------------------------------------

Starmaker:
	print "Starting Starmaker Bonus<br />\n";

	exec("php report-starmaker.php $Year $Month $NextYear $NextMonth > tmp/starmaker.txt");
		
	$Bonus = array();
	$Stars = array();
	
	$File = file('tmp/starmaker.txt');
	$Count = 0;

	foreach($File as $LineNumber => $Line)
	{
	 	list($MemberId, $StarMemberId) = explode("\t", $Line, 2);
		$MemberId -= 500000;
		$StarMemberId -= 500000;
		if (! DatabaseGet('CommissionItems', 'CommissionItemId', "Type = 'S' AND Detail LIKE '%$StarMemberId%'", 0))
		{
			if (isset($Bonus[$MemberId]))
			{
				$Bonus[$MemberId] += 100;
				$Stars[$MemberId] .= "|$StarMemberId";
			}
			else
			{
				$Bonus[$MemberId] = 100;
				$Stars[$MemberId] = $StarMemberId;
			}
		}
	}
	
	foreach ($Bonus as $MemberId => $Amount)
	{
		DatabaseInsert('CommissionItems',
			array('CommissionItemId' => 0,
				'CommissionId' => $CommissionId,
				'Type' => 'S',
				'MemberId' => $MemberId,
				'Amount' => $Amount,
				'Detail' => $Stars[$MemberId]
			)
		);
		$Count++;
	}

	print "Ending Starmaker ($Count commission item(s) generated)<br />\n";	

//-----------------------------------------------------------------------------------------------------------------
// Trinary Pool
//-----------------------------------------------------------------------------------------------------------------

TrinaryPool:
	print "Starting Trinary Pool<br />\n";

	$TotalAmount = round($CompanyCV * .01, 2);
	
	$TotalLLV = 0;
	$Percentage = array();

	$Count = 0;
	foreach($Members as $MemberId => $Member)
	{
		if ($Member['RankId'] >= $RankBronze)
		{
			$TotalLLV += GetMinVolume($MemberId);
		}
	}

	$File = fopen('detail/detail-trinary.csv', 'w');
	fputcsv($File, array('MemberId', 'LLV', 'Total LLV', 'Percentage', 'Pool Amount', 'Amount'));

	foreach($Members as $MemberId => $Member)
	{
		if ($Member['RankId'] >= $RankBronze)
		{
			$MinVol = GetMinVolume($MemberId);
			
			$Percentage = round($MinVol / $TotalLLV, 6);			
			$Amount = round($Percentage * $TotalAmount, 2);
			
			DatabaseInsert('CommissionItems',
				array('CommissionItemId' => 0,
					'CommissionId' => $CommissionId,
					'Type' => 'T',
					'MemberId' => $MemberId,
					'Amount' => $Amount,
					'Detail' => "$MinVol|$TotalLLV|$Percentage"
				)
			);

			fputcsv($File, array($MemberId + 500000, $MinVol, $TotalLLV, $Percentage, $TotalAmount, $Amount));
			
			$Count++;			
		}
	}

	fclose($File);

	function GetMinVolume($MemberId)
	{
		global $Year, $Month;
		
		$Results = DatabaseQuery("SELECT MIN(TV + PV), COUNT(*) FROM Volume INNER JOIN Members ON Volume.MemberId = Members.MemberId " .
			"WHERE UplineMemberId = $MemberId AND Year = $Year AND Month = $Month AND TreePosition <> ''");
		if (! DatabaseNumRows($Results)) { return 0; }
		$Row = DatabaseFetchRow($Results);

		if ($Row[1] < 3) { return 0; }
		return $Row[0];
	}

	print "Ending Trinary Pool  ($Count commission item(s) generated)<br />\n";	

//-----------------------------------------------------------------------------------------------------------------
// First 30 Day Bonus
//-----------------------------------------------------------------------------------------------------------------
	
FirstThirty:
	$Vol = array();
	$Results = DatabaseQuery("SELECT * FROM Volume WHERE Year = $Year AND Month = $Month");
	while ($Row = DatabaseFetchAssoc($Results)) { $Vol[$Row['MemberId']] = $Row; }

	print "Starting First 30 Day Bonus<br />\n";

	exec("php report-30-day.php $Year $Month $NextYear $NextMonth > tmp/30daybonus.txt");
		
	$Bonus = array();
	
	$File = file('tmp/30daybonus.txt');
	$Count = 0;
	
	foreach($File as $LineNumber => $Line)
	{
		list($MemberId, $Amount) = explode("\t", $Line, 2);	
	 	$MemberId = $MemberId - 500000;
		if (isset($Bonus[$MemberId]))
		{
			$Bonus[$MemberId] += $Amount;
		}
		else
		{
			$Bonus[$MemberId] = $Amount;
		}
	}
	
	foreach ($Bonus as $MemberId => $Amount)
	{
		if (($Vol[$MemberId]['PV'] >= 200) && ($Vol[$MemberId]['GV'] >= 600))
		{
			DatabaseInsert('CommissionItems',
					array('CommissionItemId' => 0,
						'CommissionId' => $CommissionId,
						'Type' => 'F',
						'MemberId' => $MemberId,
						'Amount' => round($Amount * .05, 2),
						'Detail' => floatVal($Amount)
					)
			);
			$Count++;
		}
	}
	
	print "Ending First 30 Day Bonus ($Count commission item(s) generated)<br />\n";
		
//-----------------------------------------------------------------------------------------------------------------
// Check match
//-----------------------------------------------------------------------------------------------------------------

//goto SkipEnd;

Checkmatch:
	print "Starting Check Match Bonus<br />\n";

	$File = fopen('detail/detail-checkmatch.csv', 'w');
	fputcsv($File, array('Member Id', 'Generation', 'Member Id', 'Rank', 'Amount', 'Percentage', 'SubTotal'));	

	$Count = 0;
	foreach($Members as $MemberId => $Member)
	{
		if ($Member['RankId'] >= $RankGold)
		{
			fputcsv($File, array($MemberId + 500000));

			$Detail = '';
			$Amount = Checkmatch($MemberId, 0, 0, -1, $Member['RankIndex'], true);
			if ($Amount > 0)
			{
				$Limit = $CheckMatchIndividualCaps[$Member['RankIndex']];
				if ($Amount > $Limit) { $Amount = $Limit; }

				DatabaseInsert('CommissionItems',
					array('CommissionItemId' => 0,
						'CommissionId' => $CommissionId,
						'Type' => 'C',
						'MemberId' => $MemberId,
						'Amount' => $Amount,
						'Detail' => $Detail
					)
				);
			
				$Count++;
			}
		}
	}

	function Checkmatch($MemberId, $Amount, $Rank, $Generation, $PayToRankIndex, $FirstFlag = false)
	{
		global $CheckMatchPercentages, $RankBronze, $Detail, $CommissionId, $File;
		
		$SubTotal = 0;
		
		if (($Rank >= $RankBronze) && (! $FirstFlag))
		{
			$Generation++;
			if ($Generation > 2) 
			{ 
	
				return 0;
			}
			else
			{
				
				$Percentage = $CheckMatchPercentages[$Generation][$PayToRankIndex] / 100;
				$SubTotal = round($Amount * $Percentage, 2); 
				if ($Detail) { $Detail .= '|'; }
				$Detail .= "$Generation|$MemberId|$Rank|$Amount|$Percentage|$SubTotal";		

				fputcsv($File, array('', $Generation, $MemberId + 500000, $Rank, $Amount, $Percentage, $SubTotal));		
			}
		}
		else
		{

// Change from comp plan per Ben.  Only calculate checkmatch on bronze and above.
//
//			if (($Generation > -1) && ($Amount >= 500))

			if (false)
 			{
				$Percentage = $CheckMatchPercentages[$Generation][$PayToRankIndex] / 100;
				$SubTotal = round($Amount * $Percentage, 2); 
			}
		}

		$Results = DatabaseQuery("SELECT Members.MemberId, Amount, Rank FROM Members LEFT JOIN CommissionItems " .
			"ON Members.MemberId = CommissionItems.MemberId " .
			"WHERE SponsorMemberId = $MemberId AND Members.Type = 'D' AND CommissionItems.Type = 'U' " . 
			"AND CommissionItems.CommissionId = $CommissionId");


		while ($Row = DatabaseFetchAssoc($Results))
		{
			$SubTotal += Checkmatch($Row['MemberId'], $Row['Amount'], $Row['Rank'], $Generation, $PayToRankIndex);
		}
		
		return $SubTotal;
	}	
	
	fclose($File);

	print "Ending Check Match Bonus  ($Count commission item(s) generated)<br />\n";	

//-----------------------------------------------------------------------------------------------------------------
// Rank Advancement Bonus
//-----------------------------------------------------------------------------------------------------------------

RankAdvancement:
	print "Starting Rank Advancement Bonus<br />\n";

	$TotalCount = 0;
	foreach($Members as $MemberId => $Member)
	{
		if ($Member['RankId'] >= $Rank7200)
		{
			$MemberObj = new Member($MemberId);

				$MyRanks = array(
					$MemberObj->Field['RankJanuary'], 
					$MemberObj->Field['RankFebruary'], 
					$MemberObj->Field['RankMarch'], 
					$MemberObj->Field['RankApril'], 
					$MemberObj->Field['RankMay'],
					$MemberObj->Field['RankJune'], 
					$MemberObj->Field['Rank']
			);

	
			$QualifiedRank = 10;

			$Bonus = 0;

			for($i = 70; $i > 10; $i = $i - 5)
			{
				$Count = 0;
				for ($n = 0; $n <= 	6; $n++)
				{
					if ($MyRanks[$n] >= $i) { $Count++; } else { $Count = 0; }
					if (($Count >= 3) || (($Count >= 2) && ($i < 40))) 
					{ 
						if ($i > 10)
						{
							if ($n == 6)
							{
								$Bonus += $RankAdvancementBonuses[($i / 5) - 2];
							}
						}
						break; 						
					}
				}
			}

			if ($Bonus > 0)
			{
				DatabaseInsert('CommissionItems',
					array('CommissionItemId' => 0,
						'CommissionId' => $CommissionId,
						'Type' => 'A',
						'MemberId' => $MemberId,
						'Amount' => $Bonus
					)
				);
				$TotalCount++;		
			}
		}
	}

	$Ranks = array();
	$Results = DatabaseQuery("SELECT RankId, Name FROM Ranks");
	while ($Row = DatabaseFetchAssoc($Results)) { $Ranks[$Row['RankId']] = $Row['Name']; }

	$File = fopen('detail/detail-rankadvance.csv', 'w');
	fputcsv($File, array('01/2014', '02/2014', '03/2014', '04/2014', '05/2014', '06/2014', '07/2014','08/2014','09/2014'));
	$Results = DatabaseQuery("SELECT MemberId, RankJanuary, RankFebruary, RankMarch, RankApril, RankMay, RankJune, RankJuly, RantAugest, RankSeptember, Rank FROM Members WHERE Rank > 10 ORDER BY Rank DESC");	
	while ($Row = DatabaseFetchAssoc($Results))
	{
		fputcsv($File, array($Row['MemberId'] + 500000, $Ranks[$Row['RankJanuary']], $Ranks[$Row['RankFebruary']], $Ranks[$Row['RankMarch']], $Ranks[$Row['RankApril']], $Ranks[$Row['RankMay']], $Ranks[$Row['RankJune']],$Ranks[$Row['RankJuly']],$Ranks[$Row['RankAugust']],$Ranks[$Row['RankSeptember']],$Ranks[$Row['Rank']]));	
	}	
	fclose($File);

print "Ending Rank Advancement Bonus  ($TotalCount commission item(s) generated)<br />\n";	
	
//-----------------------------------------------------------------------------------------------------------------
// Starmaker 200 Bonus
//-----------------------------------------------------------------------------------------------------------------

Starmaker200:
	print "Starting Starmaker 200 Bonus<br />\n";

	exec("php report-starmaker200.php $Year $Month $NextYear $NextMonth > tmp/starmaker200.txt");
		
	$Bonus = array();
	$Stars = array();
	
	$File = file('tmp/starmaker200.txt');
	$Count = 0;

	foreach($File as $LineNumber => $Line)
	{
	 	list($MemberId, $StarMemberId) = explode("\t", $Line, 2);
		$MemberId -= 500000;
		$StarMemberId -= 500000;
		print "Is Commission item available :: ".DatabaseGet('CommissionItems', 'CommissionItemId', "Type = 'S' AND Detail LIKE '%$StarMemberId%'", 0) ."\n";
		if (! DatabaseGet('CommissionItems', 'CommissionItemId', "Type = 'S' AND Detail LIKE '%$StarMemberId%'", 0))
		{
			if (isset($Bonus[$MemberId]))
			{
				$Bonus[$MemberId] += 25;
				$Stars[$MemberId] .= "|$StarMemberId";
			}
			else
			{
				$Bonus[$MemberId] = 25;
				$Stars[$MemberId] = $StarMemberId;
			}
		}
	}
	
	foreach ($Bonus as $MemberId => $Amount)
	{
		//DatabaseInsert('CommissionItems',
		//	array('CommissionItemId' => 0,
		//		'CommissionId' => $CommissionId,
		//		'Type' => 'S',
		//		'MemberId' => $MemberId,
		//		'Amount' => $Amount,
		//		'Detail' => $Stars[$MemberId]
		//	)
		//);
		$Count++;
	}

	print "Ending Starmaker 200 ($Count commission item(s) generated)<br />\n";	
//-----------------------------------------------------------------------------------------------------------------
// End Starmaker Bonus 200
//-----------------------------------------------------------------------------------------------------------------
//-----------------------------------------------------------------------------------------------------------------
// Starmaker Bonus 300
//-----------------------------------------------------------------------------------------------------------------

Starmaker300:
	print "Starting Starmaker 300 Bonus<br />\n";

	exec("php report-starmaker300.php $Year $Month $NextYear $NextMonth > tmp/starmaker300.txt");
		
	$Bonus = array();
	$Stars = array();
	
	$File = file('tmp/starmaker300.txt');
	$Count = 0;

	foreach($File as $LineNumber => $Line)
	{
	 	list($MemberId, $StarMemberId) = explode("\t", $Line, 2);
		$MemberId -= 500000;
		$StarMemberId -= 500000;
		if (! DatabaseGet('CommissionItems', 'CommissionItemId', "Type = 'S' AND Detail LIKE '%$StarMemberId%'", 0))
		{
			if (isset($Bonus[$MemberId]))
			{
				$Bonus[$MemberId] += 50$;
				$Stars[$MemberId] .= "|$StarMemberId";
			}
			else
			{
				$Bonus[$MemberId] = 50;
				$Stars[$MemberId] = $StarMemberId;
			}
			// TODO Validate previous paid list and update bonus to 25$
		}
	}
	
	foreach ($Bonus as $MemberId => $Amount)
	{
		//DatabaseInsert('CommissionItems',
		//	array('CommissionItemId' => 0,
		//		'CommissionId' => $CommissionId,
		//		'Type' => 'S',
		//		'MemberId' => $MemberId,
		//		'Amount' => $Amount,
		//		'Detail' => $Stars[$MemberId]
		//	)
		//);
		$Count++;
	}

	print "Ending Starmaker 300 ($Count commission item(s) generated)<br />\n";	
//------------------------------------------------------------------------------------
SkipEnd:
	print "Ending compensation engine calculation for $Month/$Year<br />\n";
	
?>
