<?php

// DjVu to HTML
require_once (dirname(__FILE__) . '/config.inc.php');
require_once (dirname(__FILE__) . '/djvu_xml.php');
require_once (dirname(__FILE__) . '/utils.php');

//--------------------------------------------------------------------------------------------------
function mean($a)
{
	$average = 0;
	$n = count($a);
	$sum = 0;
	foreach ($a as $x)
	{
		$sum += $x;
	}
	$average = $sum/$n;
	return $average;
}
	
//--------------------------------------------------------------------------------------------------
function structure($xml_filename)
{
	$page = null;
	
	$pagenum = $xml_filename;
	$pagenum = str_replace(".xml", "", $pagenum);
	
	// Get DjVu page number
	preg_match('/(?<id>\d+).xml$/', $xml_filename, $m);
	$id = $m['id'];
	
	// Get XML
	$xml = file_get_contents($xml_filename);
	
	// Remove any spurious things which break XML parsers
	$xml = clean_xml($xml);
		
	//echo $xml;
		
	$dom= new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	// Create page object from XML file to hold things such as bounding boxes
	$bbox = djvu_page_bbox($xpath);
	
	$page = new stdclass;
	$page->regions = array();
	$page->dpi = 0;
	
	// Get DPI
	$nodes = $xpath->query ('//PARAM[@name="DPI"]/@value');
	foreach($nodes as $node)
	{
		$page->dpi = $node->firstChild->nodeValue;
	}
	
	// Get physical page bounding box
	$page->bbox = array(0,0,0,0);
	$nodes = $xpath->query ('//OBJECT/@width');
	foreach($nodes as $node)
	{
		$page->bbox[2] = $node->firstChild->nodeValue;
	}
	$nodes = $xpath->query ('//OBJECT/@height');
	foreach($nodes as $node)
	{
		$page->bbox[1] = $node->firstChild->nodeValue;
	}
	$page->text_bbox = array($page->bbox[2],0,0,$page->bbox[1]);

	//------------------------------------------------------------------------------------------
	// Regions, paragraphs, and lines on page
	$regions = $xpath->query ('//REGION');		
	foreach($regions as $region)
	{
		$region_object = new stdclass;
		
		// Initialise region bounding box
		$region_object->bbox = array(10000,0,0,10000);
		
		// Paragraphs
		$region_object->paragraphs = array();
				
		$paragraphs = $xpath->query ('PARAGRAPH', $region);
		foreach ($paragraphs as $paragraph)
		{
			$paragraph_object = new stdclass;
			
			// Initialise paragraph bounding box
			$paragraph_object->bbox = array(10000,0,0,10000);
			
			// 
			$paragraph_object->line_heights = array();
			
			// Lines
			$paragraph_object->lines = array();
			$lines = $xpath->query ('LINE', $paragraph);
			foreach ($lines as $line)
			{
				$line_object = new stdclass;
				$line_object->text = '';
				
				// Add line bbox to paragraph bbox
				$line_object->bbox = djvu_line_coordinates($xpath, $line, $line_object->text);
				$paragraph_object->bbox = merge_coordinates($paragraph_object->bbox, $line_object->bbox);
				
				// Extract words
				$line_object->words = djvu_words($xpath, $line);
				
				// Font info
				$line_object->baseline = $page->bbox[1];
				$line_object->capheight = 0;
				$line_object->descender = $page->bbox[1];
				$line_object->ascender = 0;
				
				foreach ($line_object->words as $word)
				{
					//echo $word->text . " ";
				
					// Get font dimensions for this line
					
					if (preg_match('/[tdfhklb]/', $word->text))
					{
						$line_object->ascender = max($line_object->ascender, $word->bbox[3]);
						$line_object->baseline = min($line_object->baseline, $word->bbox[1]);
					}

					if (preg_match('/[qypgj]/', $word->text))
					{
						$line_object->descender = min($line_object->descender, $word->bbox[1]);
					}

					if (preg_match('/[A-Z0-9]/', $word->text))
					{
						$line_object->capheight = max($line_object->capheight, $word->bbox[3]);
						$line_object->baseline = min($line_object->baseline, $word->bbox[1]);
					}
					
				}	
				
				$line_object->fontmetrics = new stdclass;
				
				if ($line_object->baseline != $page->bbox[1])
				{
					$line_object->fontmetrics->baseline = $line_object->baseline;
				
					if ($line_object->ascender != 0)
					{
						$line_object->fontmetrics->ascender = $line_object->baseline - $line_object->ascender;
					}
					
					if ($line_object->capheight != 0)
					{
						$line_object->fontmetrics->capheight = $line_object->baseline - $line_object->capheight;
					}
					
					if ($line_object->descender != $page->bbox[1])
					{
						$line_object->fontmetrics->descender = $line_object->descender - $line_object->baseline;
					}
				}
				
				/*
				echo " ascender: " . $line_object->ascender . "\n";
				echo "capheight: " . $line_object->capheight . "\n";
				echo " baseline: " . $line_object->baseline . "\n";
				echo "descender: " . $line_object->descender . "\n";
				echo "---\n";
				echo " ascender: " . ($line_object->baseline - $line_object->ascender) . "\n";
				echo "capheight: " . ($line_object->baseline - $line_object->capheight) . "\n";
				echo " baseline: 0\n";
				echo "descender: " . ($line_object->descender - $line_object->baseline) . "\n";
				echo "---\n";
				*/
				
				
				$paragraph_object->baselines[] = $line_object->baseline;
		
				$paragraph_object->lines[] = $line_object;
			}
			
			// Add paragraph bbox to region bbox
			$region_object->bbox = merge_coordinates($region_object->bbox, $paragraph_object->bbox);		
						
			$region_object->paragraphs[] = $paragraph_object;
		}
				
		$page->regions[] = $region_object;
	}

	return $page;
}

//--------------------------------------------------------------------------------------------------
function extract_font_sizes($page)
{
	// Compute font sizes
	foreach ($page->regions as $region)
	{
		foreach ($region->paragraphs as $paragraph)
		{
			//echo count($paragraph->lines) . "\n";
			//print_r($paragraph->bbox);
			
			$fontmetrics = new stdclass;
			
			$count = 0;
			$last_baseline = 0;
			foreach ($paragraph->lines as $line)
			{
				//echo "line->fontmetrics\n";
				//print_r($line->fontmetrics);
				
				//echo $line->text . "\n";
				
				if ($count > 0 && isset($line->fontmetrics->baseline))
				{
					$fontmetrics->linespacing[] = $line->fontmetrics->baseline - $last_baseline;
				}
				$count++;
				$last_baseline = $line->fontmetrics->baseline;
				
				if ($line->fontmetrics->ascender) { $fontmetrics->ascender[] = $line->fontmetrics->ascender; }
				if ($line->fontmetrics->capheight) { $fontmetrics->capheight[] = $line->fontmetrics->capheight; }
				if ($line->fontmetrics->descender) { $fontmetrics->descender[] = $line->fontmetrics->descender; }
			}
			//print_r($fontmetrics);
			
			$paragraph->fontmetrics = new stdclass;
			
			if (isset($fontmetrics->linespacing))
			{
				$paragraph->fontmetrics->linespacing = mean($fontmetrics->linespacing);
			}
			else
			{
				$paragraph->fontmetrics->linespacing = -1;
			}
			if (isset($fontmetrics->ascender))
			{
				$paragraph->fontmetrics->ascender = mean($fontmetrics->ascender);
			}
			if (isset($fontmetrics->capheight))
			{
				$paragraph->fontmetrics->capheight = mean($fontmetrics->capheight);
			}
			if (isset($fontmetrics->descender))
			{
				$paragraph->fontmetrics->descender = mean($fontmetrics->descender);
			}
			
			//print_r($paragraph->fontmetrics);
			
		}
	}
}
		

//--------------------------------------------------------------------------------------------------
/*
	Create HTML with hOCR
*/
function export_html($page)
{
	$html = '';
	
	$scale = 0.4;
	
	$html = '<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	
	<!-- hOCR tags -->
	<meta name="ocr-capabilities" content="ocr_line ocr_page ocrx_word" />
	<meta name="ocr-langs" content="en" />
	<meta name="ocr-scripts" content="Latn" />
	<meta name="ocr-microformats" content="" />
	

	<style type="text/css">
	.body {
	   margin:0px;
	   padding:0px;
	}
	
	.ocr_page {
		border:1px solid black;
		left: 0px;
		top: 0px;
		width: ' . ($scale * $page->bbox[2]) . 'px;
		height: ' . ($scale * $page->bbox[1]) . 'px;
	}
	
	/* http://blog.vjeux.com/2011/css/css-one-line-justify.html */
	/* This ensures single line of text is justified */
.ocr_line {
  text-align: justify;
}
.ocr_line:after {
  content: "";
  display: inline-block;
  width: 100%;
}	
	</style>
</head>
<body>' . "\n";

	$html .= "<!-- page -->\n";
	
	$html .= '<div class="ocr_page"';
	$html .= ' title="bbox 0 0 ' . ($scale * $page->bbox[2]) . ' ' . ($scale * $page->bbox[1]) . '"';
	$html .= '>';
	$html .= "\n";

	
	foreach ($page->regions as $region)
	{
		$html .= '<!-- region -->' . "\n";
		$html .= '<div class="ocr_carea">' . "\n";
		if (1)
		{
			foreach ($region->paragraphs as $paragraph)
			{
				$html .= '<!-- paragraph -->' . "\n";
				$html .= '<div class="ocr_par">' . "\n";
				
				// font height
				$fontsize = 0;
				
				// Compute font height based on capheight of font
				// e.g for Times New Roman we divide by 0.662
				if (isset($paragraph->fontmetrics->capheight))
				{
					/*
					if (isset($paragraph->fontmetrics->descender))
					{
						$fontsize = $paragraph->fontmetrics->capheight + $paragraph->fontmetrics->descender;
					}
					else
					{
						$fontsize = $paragraph->fontmetrics->capheight/0.7;
					}
					*/
					
					$fontsize = $paragraph->fontmetrics->capheight/0.662;
					
				}
				
				$html .= "<!-- $fontsize -->\n";
				
				$fontsize *= $scale;
				
								
				// text
				foreach ($paragraph->lines as $line)
				{
					$html .= '<div class="ocr_line" contenteditable="true" style="font-size:' . $fontsize . 'px;line-height:' . $fontsize . 'px;position:absolute;left:' . ($line->bbox[0] * $scale) . 'px;top:' . ($line->bbox[3] * $scale)  . 'px;min-width:' . ($scale *($line->bbox[2] - $line->bbox[0])) . 'px;height:' . ($line->bbox[1] - $line->bbox[3]) . 'px;">';
					
					$html .= $line->text;
					
					$html .= '</div>'  . "\n";
				
					$n = count($line->words);
					$count = 0;					
				}
				
				$html .= '</div><!-- ocr_par -->' . "\n";
			}
		}		
		$html .= '</div><!-- ocr_carea -->' . "\n";
	}
	
	$html .= '</div><!-- ocr_page -->' . "\n";
	$html .= '</body>
</html>';

	return $html;
}

//--------------------------------------------------------------------------------------------------
/*
	Create HTML with hOCR
*/
function export_html_dom($page)
{
	global $config;
	
	$doc = new DOMDocument('1.0');

	$scale = $config['image_width']/$page->bbox[2];
	
	$html = $doc->appendChild($doc->createElement('html'));
	$head = $html->appendChild($doc->createElement('head'));

	$meta = $head->appendChild($doc->createElement('meta'));
	$meta->setAttribute('charset', 'utf-8');
	
	$meta = $head->appendChild($doc->createElement('meta'));
	$meta->setAttribute('name', 'ocr-capabilities');
	$meta->setAttribute('content', 'ocr_carea ocr_line ocr_page ocr_par');
	
	$style = $head->appendChild($doc->createElement('style'));
	$style->setAttribute('type', 'text/css');
	
	$style_text = '
	body {
	   margin:0px;
	   padding:0px;
	   background-color: #E9E9E9;
	}
	
	.ocr_page {
		background-color: white;
		box-shadow:2px 2px 10px #aaaaaa;
		/* border:1px solid black; */
		position:relative;
		left: 20px;
		top: 20px;
		width: ' . ($scale * $page->bbox[2]) . 'px;
		height: ' . ($scale * $page->bbox[1]) . 'px;
	}
	
	/* http://blog.vjeux.com/2011/css/css-one-line-justify.html */
	/* This ensures single line of text is justified */
.ocr_line {
  text-align: justify;
}
.ocr_line:after {
  content: "";
  display: inline-block;
  width: 100%;
}';
	$style->appendChild($doc->createTextNode($style_text));
	
	$body = $html->appendChild($doc->createElement('body'));
	$body->appendChild($doc->createComment('page'));
	
	$ocr_page = $body->appendChild($doc->createElement('div'));
	$ocr_page->setAttribute('class', 'ocr_page');
	$ocr_page->setAttribute('title', 'bbox 0 0 ' . ($scale * $page->bbox[2]) . ' ' . ($scale * $page->bbox[1]));

	foreach ($page->regions as $region)
	{
		$ocr_page->appendChild($doc->createComment('region'));	
		$ocr_carea = $ocr_page->appendChild($doc->createElement('div'));
		$ocr_carea->setAttribute('class', 'ocr_carea');	
		
		foreach ($region->paragraphs as $paragraph)
		{
			$ocr_carea->appendChild($doc->createComment('paragraph'));	
			$ocr_par = $ocr_carea->appendChild($doc->createElement('div'));
			$ocr_par->setAttribute('class', 'ocr_par');	
			
			// font height
			$fontsize = 0;
			
			// Compute font height based on capheight of font
			// e.g for Times New Roman we divide by 0.662
			if (isset($paragraph->fontmetrics->capheight))
			{
				$fontsize = $paragraph->fontmetrics->capheight/0.662;		
			}
			
			$linespacing = $paragraph->fontmetrics->linespacing;
			if ($linespacing != -1)
			{
				$linespacing = round($linespacing/$page->dpi * 72);
				$ocr_par->appendChild($doc->createComment($linespacing . 'pt'));
			}
				
			$fontsize *= $scale;
												
			// text
			foreach ($paragraph->lines as $line)
			{
				$ocr_par->appendChild($doc->createComment('line'));	
				$ocr_line = $ocr_par->appendChild($doc->createElement('div'));
				$ocr_line->setAttribute('class', 'ocr_line');	

				$ocr_line->setAttribute('contenteditable', 'true');	
				$ocr_line->setAttribute('class', 'ocr_line');	
				$ocr_line->setAttribute('style', 'font-size:' . $fontsize . 'px;line-height:' . $fontsize . 'px;position:absolute;left:' . ($line->bbox[0] * $scale) . 'px;top:' . ($line->bbox[3] * $scale)  . 'px;min-width:' . ($scale *($line->bbox[2] - $line->bbox[0])) . 'px;height:' . ($scale *($line->bbox[1] - $line->bbox[3])) . 'px;');	
			
				$ocr_line->appendChild($doc->createTextNode($line->text));
			
			}
		}		
	}

	return $doc->saveHTML();
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
	
	print_r($pages);
	
	foreach ($pages as $page)
	{
		$xml_filename =  $djvu_dir . '/' . $page . '.xml';
		
		$page_data = structure($xml_filename);
	
		extract_font_sizes($page_data);
		
		//$html = export_html($page_data);
		$html = export_html_dom($page_data);
		
		$html_filename = $html_dir . '/' . $page . '.html';
		
		file_put_contents($html_filename, $html);
	}
	
}



?>