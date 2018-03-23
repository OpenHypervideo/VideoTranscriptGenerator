<?php

set_time_limit(0);
date_default_timezone_set('CET');
ob_implicit_flush(true);
ob_end_flush();

// Check XML directory

if (!is_writable('input/xml/')) {
	if (!mkdir('input/xml/')) {
		echo 'input/xml/ directory missing. Make sure it exists and is writable.';

		sleep(1);
		exit();
	} else {
		chmod('input/xml/', 0755);
	}
}

// Loop through all xml files and get media IDs

$fileIndex = array();
$fileArray = array_values(array_diff(scandir('input/xml/'), array('.', '..', '.DS_Store', '_index.json')));

foreach ($fileArray as $fileName) {
	
	echo '<br>'.$fileName.'<br>';

	$xmlData = simplexml_load_file('input/xml/'.$fileName);

	$datum = (string) $xmlData->xpath('//kopfdaten//datum')[0]['date'];
	$wahlperiode = (int) $xmlData->xpath('//kopfdaten//wahlperiode')[0];
	$sitzungsnummer = (int) $xmlData->xpath('//kopfdaten//sitzungsnr')[0];

	$fileInfo = new stdClass();
	$fileInfo->title = 'Wahlperiode '.$wahlperiode.', '.$sitzungsnummer.'. Sitzung: '.$datum;
	$fileInfo->period = $wahlperiode;
	$fileInfo->meeting = $sitzungsnummer;
	$fileInfo->path = 'input/xml/'.$fileName;

	array_push($fileIndex, $fileInfo);

	// CAREFUL: This potentially send thousands of requests to the Bundestag Mediathek
	getMediaIDs(dirname(__FILE__).'/input/xml/'.$fileName);

}

file_put_contents('input/xml/_index.json', json_encode($fileIndex));

//getMediaIDs(dirname(__FILE__).'/input/xml/19023-data.xml');

/**
 * @param $XMLFilePath
 * @return mixed
 */
function getMediaIDs($XMLFilePath) {

	if (file_exists($XMLFilePath)) {
		
		$xmlData = simplexml_load_file($XMLFilePath);

		$wahlperiode = $xmlData->xpath('//kopfdaten//wahlperiode')[0];
		$sitzungsnummer = $xmlData->xpath('//kopfdaten//sitzungsnr')[0];

		$alleTOPs = $xmlData->xpath('//tagesordnungspunkt');

		if (!empty($alleTOPs)) {
			
			foreach ($alleTOPs as $tagesordnungspunkt) {

				$top = $tagesordnungspunkt['top-id'];

				if (!isset($tagesordnungspunkt['media-id'])) {
					
					$topMediaID = getMediaIDfromMediathek($wahlperiode, $sitzungsnummer, $top);

					// TODO: Counter-check multi-level TOPs ("in Verbindung mit")
					/*
					if (!$topMediaID) {
						$blockTitelItems = $xmlData->xpath('//ivz-block//ivz-titel');
						
						foreach ($blockTitelItems as $blockTitelItem) {
							
							//echo (string) $blockTitelItem[0].':';
							//echo (string) $top.'<br>';
							if ((string) $blockTitelItem[0] == (string) $top) {

								$correctTOP = $xrefItem->xpath('ancestor::ivz-block/ivz-block-titel');
								$correctTOPString = str_replace(':', '', $correctTOP[0]);

								if ($correctTOPString != (string) $top) {
									echo 'Incorrent TOP ('.$top.'). Correct TOP: '.$correctTOPString.'<br>';

									$topMediaID = getMediaIDfromMediathek($wahlperiode, $sitzungsnummer, $correctTOPString);
								}
								break;
							}
						}
					}
					*/

					if (!$topMediaID) {
						echo 'Error: Media ID not found.<br><br>';
					}

					if ($topMediaID && strlen($topMediaID) > 3) {
								
						$tagesordnungspunkt['media-id'] = $topMediaID;
						
						$tocBlockItems = $xmlData->xpath('//ivz-block');

						foreach ($tocBlockItems as $tocBlockItem) {
							
							if (isset($tocBlockItem->{'ivz-block-titel'})) {

								$cleanBlockTitel = (string) $tocBlockItem->{'ivz-block-titel'};
								$cleanBlockTitel = rtrim($cleanBlockTitel, ':');
								$cleanBlockTitel = preg_replace('/(Zusatztagesordnungspunkt)/', 'Zusatzpunkt', $cleanBlockTitel);

								//echo $cleanBlockTitel.'<br>';
								//echo (string) $top.'<br><br>';

								if ($cleanBlockTitel == (string) $top) {
									
									$tocBlockItem['media-id'] = $topMediaID;

									break;
								}

							}

						}

					}

				}

				$topString = (string) $tagesordnungspunkt->xpath('p[@klasse="T_NaS"]')[0];

				if (!$topString) {
					$topString = (string) $tagesordnungspunkt->xpath('p[@klasse="T_ZP_NaS"]')[0];
				}

				if (!$topString) {
					$topString = (string) $tagesordnungspunkt->xpath('p[@klasse="T_fett"]')[0];
				}

				if ($topString && preg_match("/(Befragung der Bundesregierung)|(Befr agung der Bundesregierung)|(Fragestunde)|(Wahl der )|(Wahl des )/", $topString)) {
					continue;
				}

				//echo $topString.'<br>';
				//echo $tagesordnungspunkt['top-id'].'<br>';

				$alleReden = $tagesordnungspunkt->xpath('rede');

				if (!empty($alleReden)) {
					
					foreach ($alleReden as $rede) {

						//print_r($rede->xpath('p//redner//vorname'));
						
						/*
						$wahlperiode = $xmlData->xpath('//kopfdaten//wahlperiode')[0];
						$sitzungsnummer = $xmlData->xpath('//kopfdaten//sitzungsnr')[0];
						$top = $tagesordnungspunkt['top-id'];
						*/
						$vorname = $rede->xpath('p//redner//vorname')[0];
						$nachname = $rede->xpath('p//redner//nachname')[0];
						$titel = '';

						if (!empty($rede->xpath('p//redner//titel'))) {
							$titel = $rede->xpath('p//redner//titel')[0];
						}

						if (!isset($rede['media-id'])) {
							$mediaID = getMediaIDfromRSS($wahlperiode, $sitzungsnummer, $top, $vorname, $nachname, $titel);

							// Doublecheck via TOC if no media ID could be found
							sleep(1);
							
							if (!$mediaID) {
								$xrefItems = $xmlData->xpath('//ivz-eintrag//xref');
								
								foreach ($xrefItems as $xrefItem) {
									
									//echo (string) $xrefItem['rid'].':';
									//echo (string) $rede['id'].'<br>';
									if ((string) $xrefItem['rid'] == (string) $rede['id']) {

										$correctTOP = $xrefItem->xpath('ancestor::ivz-block/ivz-block-titel');
										$correctTOPString = str_replace(':', '', $correctTOP[0]);

										if ($correctTOPString != (string) $top) {
											echo 'Incorrent TOP ('.$top.'). Correct TOP: '.$correctTOPString.'<br>';

											$mediaID = getMediaIDfromRSS($wahlperiode, $sitzungsnummer, $correctTOPString, $vorname, $nachname, $titel);
										}
										break;
									}
								}
							}

							if (!$mediaID) {
								echo 'Error: Media ID not found.<br>';
							}

							echo 'Name: '.$vorname.' '.$nachname.', TOP: '.$top.', MediaID: '.$mediaID.'<br><br>';

							if ($mediaID && strlen($mediaID) > 3) {
								
								$rede['media-id'] = $mediaID;
								
								$tocItems = $xmlData->xpath('//ivz-eintrag');

								foreach ($tocItems as $tocItem) {
									if (isset($tocItem->xref) && ((string) $tocItem->xref['rid'] == (string) $rede['id'])) {
										
										$tocItem['media-id'] = $mediaID;

										break;
									}
								}

							}
						}

					}

				}

			}

		} else {
			echo 'Keine Tagesordnungspunkte gefunden <br>';
		}

		file_put_contents($XMLFilePath, $xmlData->asXML());
				
	} else {
		echo 'XML file not found at '.$XMLFilePath;
	}

} 

//getMediaIDfromRSS('19', '14', 'Tagesordnungspunkt 7', 'Frauke', 'Petry', '');

/**
 * @param $wahlperiode
 * @param $sitzungsnummer
 * @param $top
 * @param $vorname
 * @param $nachname
 * @param $titel
 * @return string
 */
function getMediaIDfromRSS($wahlperiode, $sitzungsnummer, $top, $vorname, $nachname, $titel) {

	sleep(1);

	// Fix Namen
	$vorname = str_replace('Alterspräsident ', '', $vorname);
	$vorname = str_replace('Dr. ', '', $vorname);
	$vorname = str_replace('Graf ', '', $vorname);
	$vorname = str_replace(' Graf', '', $vorname);
	$nachname = str_replace('der ', '', $nachname);
	$vorname = str_replace(' Freiherr von', '', $vorname);
	$nachname = str_replace('Freiherr von', '', $nachname);
	$nachname = str_replace('von ', '', $nachname);
	$nachnameParts = explode(' ', $nachname);
	if (count($nachnameParts) == 2 
			&& $nachnameParts[0] != 'De'
			&& $nachnameParts[0] != 'Mohamed') {
		$vorname .= ' '.$nachnameParts[0];
		$nachname = $nachnameParts[1];
	}
	$vornameParts = explode(' ', $vorname);
	if (count($vornameParts) == 2 && (
		$vornameParts[1] == 'Mohamed' || 
		$vornameParts[1] == 'de'
	)) {
		$nachname = $vornameParts[1].' '.$nachname;
		$vorname = $vornameParts[0];
	}
	preg_match('/(in der)/', $vorname, $match);
	if (strlen($match[0]) != 0) {
		$vorname = str_replace($match[0], '', $vorname);
		$nachname = $match[0].' '.$nachname;
	}
	if ($vorname == 'Matern' && $nachname == 'Marschall') {
		$vorname = 'Matern von';
	}
	// Fix Ende

	$searchString = 'TOP: '.getTopShortID($top);

	//echo 'Search for: '.$searchString.'<br>';

	$nachnameClean = urlencode(convertAccentsAndSpecialToNormal($nachname));
	$vornameClean = urlencode(convertAccentsAndSpecialToNormal($vorname));

	$rssURL = 'http://webtv.bundestag.de/player/bttv/news.rss?lastName='.$nachnameClean.'&firstName='.$vornameClean.'&meetingNumber='.urlencode($sitzungsnummer).'&period='.urlencode($wahlperiode);

	echo $rssURL.'<br>';

	$rssResult = simplexml_load_file($rssURL);
	
	/*
	echo '<pre>';
	print_r($rssResult);
	echo '</pre>';
	*/
	
	$allItems = $rssResult->xpath('//item');
	
	if (count($allItems) > 1 && strlen($top) > 1) {
		
		foreach ($allItems as $item) {
			
			$description = $item->description[0];
			$description = str_replace('  ', ' ', $description);
			
			if (preg_match("/(".$searchString.")/", $description)) {
				$link = $item->link;
				$mediaID = array_pop(explode('/', $link));

				return $mediaID;
			}

		}

	} elseif (count($allItems) == 1 && strlen($top) > 1) {

		$description = $allItems[0]->description[0];
		$description = str_replace('  ', ' ', $description);
		$link = $allItems[0]->link;
		
		if (preg_match("/(".$searchString.")/", $description)) {
			$mediaID = array_pop(explode('/', $link));
			return $mediaID;
		}

	}

	return null;
	
}

/**
 * @param $wahlperiode
 * @param $sitzungsnummer
 * @param $top
 * @return string
 */
function getMediaIDfromMediathek($wahlperiode, $sitzungsnummer, $top) {

	sleep(1);

	$top = getTopShortID($top);

	$paramWahlperiode = ($wahlperiode ? '536678#'.$wahlperiode : '');
	$paramSitzungsnummer = ($sitzungsnummer ? '536680#'.$sitzungsnummer : '');
	$paramTOP = ($top ? '536682#"'.$top.'"' : '');

	$mediathekURL = 'http://www.bundestag.de/ajax/filterlist/de/mediathek/-/536668/h_4c75e2c3fa5c32bcc9f292c5630b9c40?limit=24&mediaCategory='.urlencode('442350#Plenarsitzungen').'&noFilterSet=false&wahlperiode='.urlencode($paramWahlperiode).'&sitzung='.urlencode($paramSitzungsnummer).'&visibleAgendaItemNumber='.urlencode($paramTOP);

	echo $mediathekURL.'<br>';

	$mediathekResult = file_get_contents($mediathekURL);
	
	$dom = new DOMDocument('1.0', 'UTF-8');
	
	// set error level
	$internalErrors = libxml_use_internal_errors(true);

	$dom->loadHTML($mediathekResult);

	// restore error level
	libxml_use_internal_errors($internalErrors);

	$xpath = new DOMXpath($dom);
	$result = $xpath->query('//div[@class="bt-slide col-xs-4"]//a');
	if ($result->length == 1) {
		/*
		echo '<pre>';
		print_r($result->item(0)->getAttribute('data-videoid'));
		echo '</pre>';
		*/
		return (string) $result->item(0)->getAttribute('data-videoid');
	} else {
		return null;
	}
	
}

/**
 * @param $top
 * @return string
 */
function getTopShortID($top) {
	$topParts = explode(' ', $top);
	$topType = $topParts[0];
	$topID = $topParts[1];

	if (preg_match('/-/', $topID)) {
		$topIDArray = explode('-', $topID);
		$topIDStart = (int) $topIDArray[0];
		$topIDEnd = (int) $topIDArray[1];

		$count = $topIDStart;
		$topID = $topIDArray[0];
		for($i=$topIDStart+1; $i<$topIDEnd; $i++) {
			$topID .= ','.$i;
		}
	}

	if ($topType == 'Zusatzpunkt') {
		return 'ZP '.$topID;
	} else {
		return ''.$topID;
	}
}

/*
 * Replaces special characters in a string with their "non-special" counterpart.
 *
 * Useful for friendly URLs.
 *
 * @access public
 * @param string
 * @return string
 */
function convertAccentsAndSpecialToNormal($string) {
	$table = array(
		'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Ă'=>'A', 'Ā'=>'A', 'Ą'=>'A', 'Æ'=>'A', 'Ǽ'=>'A',
		'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'ă'=>'a', 'ā'=>'a', 'ą'=>'a', 'æ'=>'a', 'ǽ'=>'a',

		'Þ'=>'B', 'þ'=>'b', 'ß'=>'s',

		'Ç'=>'C', 'Č'=>'C', 'Ć'=>'C', 'Ĉ'=>'C', 'Ċ'=>'C',
		'ç'=>'c', 'č'=>'c', 'ć'=>'c', 'ĉ'=>'c', 'ċ'=>'c',

		'Đ'=>'Dj', 'Ď'=>'D', 'Đ'=>'D',
		'đ'=>'dj', 'ď'=>'d',

		'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ĕ'=>'E', 'Ē'=>'E', 'Ę'=>'E', 'Ė'=>'E',
		'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ĕ'=>'e', 'ē'=>'e', 'ę'=>'e', 'ė'=>'e',

		'Ĝ'=>'G', 'Ğ'=>'G', 'Ġ'=>'G', 'Ģ'=>'G',
		'ĝ'=>'g', 'ğ'=>'g', 'ġ'=>'g', 'ģ'=>'g',

		'Ĥ'=>'H', 'Ħ'=>'H',
		'ĥ'=>'h', 'ħ'=>'h',

		'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'İ'=>'I', 'Ĩ'=>'I', 'Ī'=>'I', 'Ĭ'=>'I', 'Į'=>'I',
		'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'į'=>'i', 'ĩ'=>'i', 'ī'=>'i', 'ĭ'=>'i', 'ı'=>'i',

		'Ĵ'=>'J',
		'ĵ'=>'j',

		'Ķ'=>'K',
		'ķ'=>'k', 'ĸ'=>'k',

		'Ĺ'=>'L', 'Ļ'=>'L', 'Ľ'=>'L', 'Ŀ'=>'L', 'Ł'=>'L',
		'ĺ'=>'l', 'ļ'=>'l', 'ľ'=>'l', 'ŀ'=>'l', 'ł'=>'l',

		'Ñ'=>'N', 'Ń'=>'N', 'Ň'=>'N', 'Ņ'=>'N', 'Ŋ'=>'N',
		'ñ'=>'n', 'ń'=>'n', 'ň'=>'n', 'ņ'=>'n', 'ŋ'=>'n', 'ŉ'=>'n',

		'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ō'=>'O', 'Ŏ'=>'O', 'Ő'=>'O', 'Œ'=>'O',
		'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ō'=>'o', 'ŏ'=>'o', 'ő'=>'o', 'œ'=>'o', 'ð'=>'o',

		'Ŕ'=>'R', 'Ř'=>'R',
		'ŕ'=>'r', 'ř'=>'r', 'ŗ'=>'r',

		'Š'=>'S', 'Ŝ'=>'S', 'Ś'=>'S', 'Ş'=>'S',
		'š'=>'s', 'ŝ'=>'s', 'ś'=>'s', 'ş'=>'s',

		'Ŧ'=>'T', 'Ţ'=>'T', 'Ť'=>'T',
		'ŧ'=>'t', 'ţ'=>'t', 'ť'=>'t',

		'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ũ'=>'U', 'Ū'=>'U', 'Ŭ'=>'U', 'Ů'=>'U', 'Ű'=>'U', 'Ų'=>'U',
		'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ũ'=>'u', 'ū'=>'u', 'ŭ'=>'u', 'ů'=>'u', 'ű'=>'u', 'ų'=>'u',

		'Ŵ'=>'W', 'Ẁ'=>'W', 'Ẃ'=>'W', 'Ẅ'=>'W',
		'ŵ'=>'w', 'ẁ'=>'w', 'ẃ'=>'w', 'ẅ'=>'w',

		'Ý'=>'Y', 'Ÿ'=>'Y', 'Ŷ'=>'Y',
		'ý'=>'y', 'ÿ'=>'y', 'ŷ'=>'y',

		'Ž'=>'Z', 'Ź'=>'Z', 'Ż'=>'Z', 'Ž'=>'Z',
		'ž'=>'z', 'ź'=>'z', 'ż'=>'z', 'ž'=>'z'
	);

	$string = strtr($string, $table);
	// Currency symbols: £¤¥€  - we dont bother with them for now
	$string = preg_replace("/[^\x9\xA\xD\x20-\x7F]/u", "", $string);

	return $string;
}

?>