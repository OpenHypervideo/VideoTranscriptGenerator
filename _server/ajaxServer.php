<?php

require_once('./functions.php');

header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 2020 05:00:00 GMT');
header('Content-type: application/json');

$return['status'] = 'fail';
$return['code'] = '404';
$return['string'] = 'No action was taken';

switch($_REQUEST['a']) {

	case 'forceAlign':
		$return = forceAlignXMLData($_REQUEST['xmlPath'], $_REQUEST['xPath']);
		break;

	case 'fileUpload':
		$return = fileUpload($_REQUEST['type'],$_REQUEST['name']);
		break;

	case 'getXMLFiles':
		$return = getXMLFiles();
		break;

	default:
		$return['status'] = 'success';
		$return['code'] = 0;
		$return['string'] = 'Action not recognized.';
		break;
}

?>