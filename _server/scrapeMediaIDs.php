<?php

set_time_limit(0);
date_default_timezone_set('CET');
ob_implicit_flush(true);
ob_end_flush();

// Loop through all xml files and get media IDs

$fileArray = array_values(array_diff(scandir('input/xml/'), array('.', '..', '.DS_Store')));

foreach ($fileArray as $fileName) {
	
	echo '<br>'.$fileName.'<br>';

	// CAREFUL: This potentially send thousands of requests to the Bundestag Mediathek
	getMediaIDs(dirname(__FILE__).'/input/xml/'.$fileName);

}

//getMediaIDs(dirname(__FILE__).'/input/xml/19016-data.xml');

/**
 * @param $XMLFilePath
 * @return mixed
 */
function getMediaIDs($XMLFilePath) {

	sleep(1);

	if (file_exists($XMLFilePath)) {
		
		$xmlData = simplexml_load_file($XMLFilePath);

		$alleTOPs = $xmlData->xpath('//tagesordnungspunkt');

		if (!empty($alleTOPs)) {
			
			foreach ($alleTOPs as $tagesordnungspunkt) {

				//echo $tagesordnungspunkt['top-id'].'<br>';

				$alleReden = $tagesordnungspunkt->xpath('rede');

				if (!empty($alleReden)) {
					
					foreach ($alleReden as $rede) {

						//print_r($rede->xpath('p//redner//vorname'));
						
						$wahlperiode = $xmlData->xpath('//kopfdaten//wahlperiode')[0];
						$sitzungsnummer = $xmlData->xpath('//kopfdaten//sitzungsnr')[0];
						$top = $tagesordnungspunkt['top-id'];
						$vorname = $rede->xpath('p//redner//vorname')[0];
						$nachname = $rede->xpath('p//redner//nachname')[0];
						$titel = '';

						if (!empty($rede->xpath('p//redner//titel'))) {
							$titel = $rede->xpath('p//redner//titel')[0];
						}

						$vorname = str_replace('Alterspräsident ', '', $vorname);
						$vorname = str_replace('Dr. ', '', $vorname);
						$nachnameParts = explode(' ', $nachname);
						
						if (count($nachnameParts) == 2) {
							$vorname .= ' '.$nachnameParts[0];
							$nachname = $nachnameParts[1];
						}

						if (!isset($rede['media-id'])) {
							$mediaID = getMediaIDfromRSS($wahlperiode, $sitzungsnummer, $top, $vorname, $nachname, $titel);

							if (strlen($mediaID) > 3) {
								$rede['media-id'] = $mediaID;
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

	$rssURL = 'http://webtv.bundestag.de/player/bttv/news.rss?lastName='.urlencode($nachname).'&firstName='.urlencode($vorname).'&meetingNumber='.urlencode($sitzungsnummer).'&period='.urlencode($wahlperiode);

	echo $rssURL.'<br>';

	$rssResult = simplexml_load_file($rssURL);
	
	/*
	echo '<pre>';
	print_r($rssResult);
	echo '</pre>';
	*/

	
	$allItems = $rssResult->xpath('//item');
	
	if (count($allItems) > 1 && strlen($top) > 1) {
		
		$topParts = explode(' ', $top);
		$topType = $topParts[0];
		$topID = $topParts[1];
		
		if ($topType == 'Zusatzpunkt') {
			$searchString = 'TOP: ZP '.$topID;
		} else {
			$searchString = 'TOP: '.$topID;
		}
		
		foreach ($allItems as $item) {
			
			$description = $item->description[0];
			
			if (strpos($description, $searchString) !== false) {
				$link = $item->link;
				$mediaID = array_pop(explode('/', $link));

				return $mediaID;
			}

		}

	} else {

		$link = $allItems[0]->link;
		$mediaID = array_pop(explode('/', $link));

		return $mediaID;

	}
	

}

?>