<?php
 
// Include Composer autoloader if not already done.
include dirname(__FILE__) . "/../../php/vendor/autoload.php";

error_reporting(E_ALL);
ini_set('display_errors', true);

try {

	// following is checking for malware. see: http://php.net/manual/en/features.file-upload.php

	try {

		// Undefined | Multiple Files | $_FILES Corruption Attack
		// If this request falls under any of them, treat it invalid.
		if (
				!isset($_FILES['file']['error']) ||
				is_array($_FILES['file']['error'])
				) {
					throw new RuntimeException('Invalid parameters.');
				}

				// Check $_FILES['upfile']['error'] value.
				switch ($_FILES['file']['error']) {
					case UPLOAD_ERR_OK:
						break;
					case UPLOAD_ERR_NO_FILE:
						throw new RuntimeException('No file sent.');
					case UPLOAD_ERR_INI_SIZE:
					case UPLOAD_ERR_FORM_SIZE:
						throw new RuntimeException('Exceeded filesize limit.');
					default:
						throw new RuntimeException('Unknown errors.');
				}

				// You should also check filesize here.
				if ($_FILES['file']['size'] > 1000000) {
					throw new RuntimeException('Exceeded filesize limit.');
				}

				// DO NOT TRUST $_FILES['file']['mime'] VALUE !!
				// Check MIME Type by yourself.
				$finfo = new finfo(FILEINFO_MIME_TYPE);
				if (false === $ext = array_search(
						$finfo->file($_FILES['file']['tmp_name']),
						array(
								'pdf' => 'application/pdf'
						),
						true
						)) {
							throw new RuntimeException('Invalid file format.');
						}

						$parser = new \Smalot\PdfParser\Parser();
						$pdf = $parser->parseFile($_FILES['file']['tmp_name']);						
						$theText = $pdf->getText();
						
						http_response_code(200);					//See https://docs.angularjs.org/api/ng/service/$http#json-vulnerability-protection
						echo ")]}',\n" . $theText;	// )]}', is used to stop JSON attacks, angular strips it off for you.
						return;
					
} catch (RuntimeException $e) {
	echo $e->getMessage();
}
} catch (RuntimeException $e) {
	echo $e->getMessage();
}
?>
						