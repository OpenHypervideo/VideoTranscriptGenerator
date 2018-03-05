<?php

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

set_time_limit(0);
date_default_timezone_set('CET');
ob_implicit_flush(true);
ob_end_flush();
//disable_ob();

// CONFIG
$conf['inputXML'] = 'input/xml/';
$conf['inputAudio'] = 'input/audio/';
$conf['output'] = 'output/';
$conf['pythonVersion'] = '2.7';


/**
 * @param $XMLFilePath
 * @param $xPathSelector
 * @return mixed
 */
function getXMLFiles() {
	
	global $conf;

	$fileArray = array_values(array_diff(scandir($conf['inputXML']), array('.', '..', '.DS_Store')));
	$fileArrayPaths = array_map(function($val) { global $conf; return $conf['inputXML'].$val; }, $fileArray);
	
	$response = array(  'message' => 'Files found.', 
						'status' => 'success',
						'files' => $fileArrayPaths);
	echo json_encode($response);

}

/**
 * @param $XMLFilePath
 * @param $xPathSelector
 * @return mixed
 */
function forceAlignXMLData($XMLFilePath, $xPathSelector) {

	global $conf;
	
	sleep(1);

	checkDirectories();

	if (file_exists($XMLFilePath)) {
		
		$shortXMLPath = explode($conf['inputXML'],$XMLFilePath)[1];

		$response = array(  'message' => 'XML file found ('.$shortXMLPath.').', 
							'task' => 'optimise',
							'status' => 'success',
							'progress' => 10);
		echo json_encode($response);
		
		
		$xmlData = simplexml_load_file($XMLFilePath);

		$xPathElements = $xmlData->xpath($xPathSelector);

		$rID = 0;

		if (!empty($xPathElements)) {
			
			$response = array(  'message' => 'Selected XML node exists ('.$xPathSelector.').', 
								'task' => 'optimise',
								'status' => 'success',
								'progress' => 0);
			echo json_encode($response);

			$response = array(  'message' => 'Optimising XML file...', 
								'task' => 'optimise',
								'status' => '',
								'progress' => 0);
			echo json_encode($response);

			foreach ($xPathElements as $xPathElement) {

				$sID = 0;

				if ($xPathElement->getName() == 'tagesordnungspunkt') {
					$fileNameSuffix = '-'.strtolower( mb_ereg_replace("([^\w\d\-_~,;\[\]\(\).])", '-', $xPathElement['top-id']) );
				} elseif ($xPathElement->getName() == 'rede') {
					$fileNameSuffix = '-rede-'.strtolower( mb_ereg_replace("([^\w\d\-_~,;\[\]\(\).])", '-', $xPathElement['id']) );
				} else {
					$fileNameSuffix = '';
				}

				$fileNameSuffix = mb_ereg_replace("([\.]{2,})", '', $fileNameSuffix);

				// Use xpath directly on $xmlData ($xPathElement still contains refs for all p nodes)
				foreach ($xmlData->xpath($xPathSelector.'//p') as $paragraph) {

					if ($paragraph['klasse'] == 'T_NaS' ||
						$paragraph['klasse'] == 'T_fett' ||
						$paragraph['klasse'] == 'J' ||
						$paragraph['klasse'] == 'J_1' ||
						$paragraph['klasse'] == 'O' ||
						$paragraph['klasse'] == 'Z' ||
						$paragraph['klasse'] == 'T') {

						$sentences = preg_split('/([.,:;?!\\-\\-\\–] +)/', $paragraph[0], -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

						$paragraph[0] = '';
						
						$count = 0;
						$lastChild = null;

						foreach ( $sentences as $sentence ) {

							if ( $count%2 && $lastChild ) {
								$lastChild[0] .= $sentence;
							} else {
								$newChild = $paragraph->addChild('span', $sentence);
								$newChild['class'] = 'timebased';
								
								$newChild['id'] = 's'.sprintf('%06d', ++$sID);

								$lastChild = $newChild;
							}

							$count++;
							
						}
					}

				}

			}

			$xmlFilePathArray = preg_split("/\\//", $XMLFilePath);
			$xmlFileName = array_pop($xmlFilePathArray);
			$xmlFileNameArray = preg_split("/\./", $xmlFileName);

			$optimisedXMLFileName = $xmlFileNameArray[0].$fileNameSuffix.'_optimised.xml';

			file_put_contents(dirname(__FILE__).'/output/'.$optimisedXMLFileName, $xmlData->asXML());

			$response = array(  'message' => 'Optimised XML file saved to: /output/'.$optimisedXMLFileName, 
								'task' => 'optimise',
								'status' => '',
								'progress' => 100);
			echo json_encode($response);
			
					
			if ($xmlData->xpath($xPathSelector)[0]['media-id']) {
				
				$response = array(  'message' => 'Media ID found in XML.', 
									'task' => '',
									'status' => 'success',
									'progress' => 0);
				echo json_encode($response);
				

				$audioFilePath = getAudioSource($xmlData->xpath($xPathSelector)[0]['media-id']);
				$audioFilePathArray = preg_split("/\\//", $audioFilePath);
				$audioFileName = array_pop($audioFilePathArray);

				if (!file_exists($conf['inputAudio'].$audioFileName)) {
					$response = array(  'message' => 'Audio file not found. Downloading file from Bundestag...', 
										'task' => '',
										'status' => '',
										'progress' => 0);
					echo json_encode($response);
					

					getAudioFile($audioFilePath);
				} else {
					$response = array(  'message' => 'Audio file found ('.$conf['inputAudio'].$audioFileName.'). No download necessary.', 
										'task' => 'download',
										'status' => 'success',
										'progress' => 100);
					echo json_encode($response);
					
				}

				if (!file_exists($conf['output'].$xmlFileNameArray[0].$fileNameSuffix.'_timings.json')) {
					$response = array(  'message' => 'JSON timings file not found. Force align necessary.', 
										'task' => 'forcealign',
										'status' => '',
										'progress' => 0);
					echo json_encode($response);
					

					forceAlignAudio($conf['inputAudio'].$audioFileName, $conf['output'].$optimisedXMLFileName, $conf['output'].$xmlFileNameArray[0].$fileNameSuffix.'_timings.json');
				} else {
					$response = array(  'message' => 'JSON timings file found ('.$conf['output'].$xmlFileNameArray[0].$fileNameSuffix.'_timings.json). Force Align not necessary.', 
										'task' => 'forcealign',
										'status' => 'success',
										'progress' => 100);
					echo json_encode($response);
					
				}

				$xmlDataWithTimings = getXMLWithTimings($conf['output'].$xmlFileNameArray[0].$fileNameSuffix.'_timings.json', $xmlData->asXML());

				file_put_contents($conf['output'].$xmlFileNameArray[0].$fileNameSuffix.'_timings.xml', $xmlDataWithTimings);

				$response = array(  'message' => 'XML with timings saved to: '.$conf['output'].$xmlFileNameArray[0].$fileNameSuffix.'_timings.xml', 
									'task' => '',
									'status' => '',
									'progress' => 0);
				echo json_encode($response);
				

				$simpleXMLWithTimings = new SimpleXMLElement($xmlDataWithTimings);
				$selectedXMLPart = $simpleXMLWithTimings->xpath($xPathSelector);
				$htmlString = getHTMLfromXML($selectedXMLPart[0]->asXML());

				file_put_contents($conf['output'].$xmlFileNameArray[0].$fileNameSuffix.'.html', $htmlString);

				$response = array(  'message' => 'HTML with timings saved to: '.$conf['output'].$xmlFileNameArray[0].$fileNameSuffix.'.html', 
									'task' => '',
									'status' => '',
									'progress' => 0);
				echo json_encode($response);

				$videoURL = getVideoSource($xmlData->xpath($xPathSelector)[0]['media-id']);
				
				//$videoURL = rtrim(explode('video="',$videoString)[1], '"');

				$response = array(  'message' => 'Process completed successfully.', 
									'task' => '',
									'status' => 'success',
									'progress' => 100,
									'video' => $videoURL,
									'html' => $htmlString );
				echo json_encode($response);

				unlink($conf['output'].$optimisedXMLFileName);

				sleep(1);
				
				
			} else {
				$response = array(  'message' => 'Media ID not found in XML (selected node needs attribute media-id="").', 
									'task' => '',
									'status' => 'error',
									'progress' => 0);
				echo json_encode($response);
				sleep(1);
			}

		} else {
			$response = array(  'message' => 'Selected XML node does not exist ('.$xPathSelector.').', 
								'task' => 'optimise',
								'status' => 'error',
								'progress' => 0);
			echo json_encode($response);
			sleep(1);
		}
		
	} else {
		$response = array(  'message' => 'XML file not found at '.$XMLFilePath.'.', 
							'task' => '',
							'status' => 'error',
							'progress' => 10);
		echo json_encode($response);
		sleep(1);
	}

} 

/**
 * @param $audioFilePath
 * @return mixed
 */
function getAudioFile($audioFilePath) {
	
	global $conf;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $audioFilePath);
	//curl_setopt($ch, CURLOPT_BUFFERSIZE,128);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'progress');
	curl_setopt($ch, CURLOPT_NOPROGRESS, false);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	$output = curl_exec($ch);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	$filePathArray = preg_split("/\\//", $audioFilePath);
	$audioFileName = array_pop($filePathArray);

	if ($status == 200) {
		file_put_contents($conf['inputAudio'].$audioFileName, $output);
	}

	$response = array(  'message' => 'Audio file successfully downloaded.', 
						'task' => 'download',
						'status' => 'success',
						'progress' => 100);
	echo json_encode($response);

}

/**
 * @param $resource
 * @param $download_size
 * @param $downloaded
 * @param $upload_size
 * @param $uploaded
 * @return mixed
 */
function progress($resource,$download_size, $downloaded, $upload_size, $uploaded) {
	
	if ($download_size > 0) {
		
		$response = array(  'message' => 'Audio file downloading', 
						'task' => 'download',
						'status' => '',
						'progress' => $downloaded / $download_size  * 100);
		echo json_encode($response);

	}

}

/**
 * @param $audioFilePath
 * @param $optimisedXMLFilePath
 * @param $outputFilePath
 * @return mixed
 */
function forceAlignAudio($audioFilePath, $optimisedXMLFilePath, $outputFilePath) {
	
	global $conf;

	$secureAudioPath = escapeshellcmd($audioFilePath);
	$secureXMLPath = escapeshellcmd($optimisedXMLFilePath);
	$secureOutputPath = escapeshellcmd($outputFilePath);
	$exec_enabled =
	   function_exists('exec') &&
	   !in_array('exec', array_map('trim', explode(', ', ini_get('disable_functions')))) &&
	   strtolower(ini_get('safe_mode')) != 1
	;

	if (!$exec_enabled) {
		$response = array(  'message' => 'PHP shell exec not allowed. Aeneas can not be executed.', 
							'task' => 'forcealign',
							'status' => 'error',
							'progress' => 40);
		echo json_encode($response);
		sleep(1);
		exit();
	}
	
	$aeneasToolsPath = exec('python'.$conf['pythonVersion'].' -c "import aeneas.tools; print aeneas.tools.__file__"');

	if (strlen($aeneasToolsPath) == 0) {
		$response = array(  'message' => 'Aeneas not found. Please install Aeneas.', 
							'task' => 'forcealign',
							'status' => 'error',
							'progress' => 40);
		echo json_encode($response);
		sleep(1);
		exit();
	}

	$aeneasExecuteTask = preg_split('/(__init__.pyc)/', $aeneasToolsPath)[0].'execute_task.pyc';
	$pythonAeneasPathArray = preg_split('/(\\/lib)/',$aeneasExecuteTask);
	$env = $pythonAeneasPathArray[0].'/bin';

	// only applied temporarily for this request
	putenv('PATH=$PATH:'.$env);

	$command = 'export PYTHONIOENCODING=UTF-8 && '.$env.'/python'.$conf['pythonVersion'].' '.$aeneasExecuteTask.' '.$secureAudioPath.' '.$secureXMLPath.' "task_language=deu|os_task_file_format=json|is_text_type=unparsed|is_text_unparsed_id_regex=s[0-9]+|is_text_unparsed_id_sort=numeric|task_adjust_boundary_no_zero=true|task_adjust_boundary_nonspeech_min=2|task_adjust_boundary_nonspeech_string=REMOVE" '.$secureOutputPath.' 2>&1';
	
	//|task_adjust_boundary_algorithm=rateaggressive
	//|task_adjust_boundary_rate_value=21
	//|task_adjust_boundary_nonspeech_string=REMOVE


	/*
	$descriptorspec = array(
	   0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
	   1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
	   2 => array("pipe", "w")    // stderr is a pipe that the child will write to
	);
	flush();
	$process = proc_open($command, $descriptorspec, $pipes, realpath('./'), array());
	echo "<pre>";
	if (is_resource($process)) {
	    while ($s = fgets($pipes[1])) {
	        echo $s;
	        flush();
	    }
	}
	echo "</pre>";
	*/
	
	$output = exec($command);
	
	if (strpos($output, '[INFO] Created file ') !== false) {
		$response = array(  'message' => 'Force Align success. Aeneas Output: '.$output, 
							'task' => 'forcealign',
							'status' => 'success',
							'progress' => 100);
		echo json_encode($response);
	} else {
		$response = array(  'message' => 'Force Align error. Output: '.$output, 
							'task' => 'forcealign',
							'status' => 'error',
							'progress' => 40);
		echo json_encode($response);

		sleep(1);
		exit();
	}
	
}

/**
 * @param $timingsFilePath (JSON)
 * @param $optimisedXMLData
 * @return mixed
 */
function getXMLWithTimings($timingsFilePath, $optimisedXMLData) {

	$timingsString = file_get_contents($timingsFilePath);
	$timingsData = json_decode($timingsString, true);

	$xmlData = new SimpleXMLElement($optimisedXMLData);

	foreach ($timingsData['fragments'] as $fragment) {
		$spanElement = $xmlData->xpath('//span[@id="'.$fragment['id'].'"]')[0];
		$spanElement['data-start'] = $fragment['begin'];
		$spanElement['data-end'] = $fragment['end'];
		unset($spanElement['id']);
	}

	return $xmlData->asXML();
	
}

/**
 * @param $mediaID
 * @return mixed
 */
function getAudioSource($mediaID) {

	$audioPath = 'http://static.cdn.streamfarm.net/1000153copo/ondemand/145293313/'.$mediaID.'/'.$mediaID.'_mp3_128kb_stereo_de_128.mp3';
	
	return $audioPath;

}

/**
 * @param $mediaID
 * @return mixed
 */
function getVideoSource($mediaID) {

	$videoPath = 'http://static.cdn.streamfarm.net/1000153copo/ondemand/145293313/'.$mediaID.'/'.$mediaID.'_h264_1920_1080_5000kb_baseline_de_5000.mp4';
	
	return $videoPath;

}

/**
 * @param $XMLDataWithTimings
 * @return mixed
 */
function getHTMLfromXML($XMLDataWithTimings) {

	$xmlString = $XMLDataWithTimings;

	$xmlStr = array(
		'<?xml version="1.0" encoding="UTF-8"?>
',
		'<?xml version="1.0"?>
',
		'<!DOCTYPE dbtplenarprotokoll SYSTEM "dbtplenarprotokoll.dtd">',
		'dbtplenarprotokoll', 
		'<sitzungsverlauf>',
		'</sitzungsverlauf>',
		'<tagesordnungspunkt ', 
		'</tagesordnungspunkt>',
		'<kommentar>',
		'</kommentar>',
		'<rede ',
		'</rede>'
	);
	$htmlStr = array(
		'<!DOCTYPE html>
<html>
  <body>
',
		'<!DOCTYPE html>
<html>
  <body>
',
		'',
		'body', 
		'<div class="sitzungsverlauf">',
		'</div>',
		'    <div class="tagesordnungspunkt" ',
		'</div>', 
		'<div class="kommentar">',
		'</div>',
		'<div class="rede" ',
		'</div>'
	);

	$htmlString = str_replace($xmlStr, $htmlStr, $xmlString);

	//remove speaker info
	$htmlString = preg_replace('/(<redner)(.|\n)*?(redner>)/', '', $htmlString);

	return '<!DOCTYPE html>
<html>
  <head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  </head>
  <body>
'.$htmlString.'
  </body>
</html>';
	
}

function disable_ob() {
    // Turn off output buffering
    ini_set('output_buffering', 'off');
    // Turn off PHP output compression
    ini_set('zlib.output_compression', false);
    // Implicitly flush the buffer(s)
    ini_set('implicit_flush', true);
    ob_implicit_flush(true);
    // Clear, and turn off output buffering
    while (ob_get_level() > 0) {
        // Get the curent level
        $level = ob_get_level();
        // End the buffering
        ob_end_clean();
        // If the current level has not changed, abort
        if (ob_get_level() == $level) break;
    }
    // Disable apache output buffering/compression
    if (function_exists('apache_setenv')) {
        apache_setenv('no-gzip', '1');
        apache_setenv('dont-vary', '1');
    }
}

function checkDirectories() {

	global $conf;

	if (!is_writable($conf['inputXML'])) {
		if (!mkdir($conf['inputXML'])) {
			$response = array(  'message' => 'Directory missing: '.$conf['inputXML'].' Please make sure it exists and is writable.', 
								'task' => 'Generate Directories',
								'status' => 'error',
								'progress' => 0);
			echo json_encode($response);

			sleep(1);
			exit();
		} else {
			chmod($conf['inputXML'], 0755);
		}
	}

	if (!is_writable($conf['inputAudio'])) {
		if (!mkdir($conf['inputAudio'])) {
			$response = array(  'message' => 'Directory missing: '.$conf['inputAudio'].' Please make sure it exists and is writable.', 
								'task' => 'Generate Directories',
								'status' => 'error',
								'progress' => 0);
			echo json_encode($response);

			sleep(1);
			exit();
		} else {
			chmod($conf['inputAudio'], 0755);
		}
	}

	if (!is_writable($conf['output'])) {
		if (!mkdir($conf['output'])) {
			$response = array(  'message' => 'Directory missing: '.$conf['output'].' Please make sure it exists and is writable.', 
								'task' => 'Generate Directories',
								'status' => 'error',
								'progress' => 0);
			echo json_encode($response);

			sleep(1);
			exit();
		} else {
			chmod($conf['output'], 0755);
		}
	}

}

?>