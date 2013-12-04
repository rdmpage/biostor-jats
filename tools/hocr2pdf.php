<?php

// DjVu to HTML
require_once (dirname(__FILE__) . '/config.inc.php');
require_once (dirname(__FILE__) . '/djvu_xml.php');
require_once (dirname(__FILE__) . '/utils.php');
require_once(dirname(dirname(__FILE__)) . '/fpdf17/fpdf.php');


//--------------------------------------------------------------------------------------------------
function djvu_dimensions($djvu_dir, $page)
{
	$xml_filename =  $djvu_dir . '/' . $page . '.xml';

	// Get XML
	$xml = file_get_contents($xml_filename);
	
	// Remove any spurious things which break XML parsers
	$xml = clean_xml($xml);
		
	//echo $xml;
	$dom= new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	$page_dimensions = djvu_page_size($xpath);
	
	return $page_dimensions;
}

//--------------------------------------------------------------------------------------------------

$dir = '';
if ($argc < 2)
{
	echo "Usage: " . str_replace(dirname(__FILE__) . '/', '', __FILE__) . " <directory> \n";
	exit(1);
}
else
{
	$dir = $argv[1];
	
	if (preg_match('/(?<path>(.*))\/(?<id>\d+)$/', $dir, $m))
	{
		$reference_id = $m['id'];
	}
	else
	{
		exit();
	}


	$pages = get_jats_pages($dir, $reference_id);
	
	$djvu_dir = $dir . '/djvu';
	$html_dir = $dir . '/html';
	$pdf_filename = $dir . '/' . $reference_id . '.pdf';

	// Page dimensions
	$page_dimensions = djvu_dimensions($djvu_dir, $pages[0]);
	
	// Page scans
	$images = get_jats_page_images($dir, $reference_id);
	
	
	// Get size of image 
	$image_filename = $dir . '/' . $images[0];
	$image_info = getimagesize($image_filename);
	$image_width = $image_info[0];
	$image_height = $image_info[1];
	
	/*
	echo "image_width=$image_width\n";
	echo "page_dimensions->width " . $page_dimensions->width . "\n";
	print_r($page_dimensions);
	*/
	
	
	// PDF units are inches, so adjust DPI to match image size
	$dpi = $page_dimensions->dpi * ($image_width / $page_dimensions->width);
	$scale = 1/$dpi;
	
	// Create PDF
	$page_width_inches = $image_width / $dpi;
	$page_height_inches = $image_height / $dpi;
	
	$pdf = new FPDF('P','in',array($page_width_inches, $page_height_inches));
	$pdf->SetMargins(0,0);
	
	// Need this to avoid words spilling over to next page
	// http://www.sitepoint.com/forums/showthread.php?637008-FPDF-FPDI-Margin-Problem
	$pdf->SetAutoPageBreak(true, 0);
	
	$f = 12;
	$pdf->SetFont('Times', 'I', $f);
	
	// pages
	$num_pages = count($pages);
	for ($k = 0; $k < $num_pages; $k++)
	{
		$pdf->AddPage();
		
		// hOCR text
		
		$html_filename = $html_dir . '/' . $pages[$k] . '.html';	
		$html = file_get_contents($html_filename);
		
		$dom= new DOMDocument;
		$dom->loadHTML($html);
		$xpath = new DOMXPath($dom);
				
		$lines = $xpath->query ('//div[@class="ocr_line"]');
		foreach($lines as $line)
		{
			if ($line->hasAttributes()) 
			{ 
				$attributes = array();
				$attrs = $line->attributes; 
				
				foreach ($attrs as $i => $attr)
				{
					$attributes[$attr->name] = $attr->value; 
				}
			}
			
			$text = $line->firstChild->nodeValue;
			$text = htmlspecialchars_decode($text);
			
			// coordinates
			$coords = explode(" ", $attributes['title']);
			
			$x = $coords[1] / $dpi;
			$y = $coords[2] / $dpi;
			$w = ($coords[3] - $coords[1])  / $dpi;
			$h = ($coords[4] - $coords[2])  / $dpi;
						
			$f = $h * 72;
						
			$f = ceil($f);
			if ($f & 1)
			{
				$f++;
			}
			
			$pdf->SetFontSize($f);
			
			$text = utf8_decode($text);
			
			//echo $text . "\n";
				
			$pdf->SetXY($x, $y);
			$pdf->Cell($w, $h, $text, 0);
			
		}

		// Place image over text
		$image_filename = $dir . '/' . $images[$k];	
		$pdf->Image($image_filename, 0, 0, $page_width_inches);
	}	
	

	$pdf->SetCompression(false);
	$pdf->Output($pdf_filename, 'F');
}	
	
?>