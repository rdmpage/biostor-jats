<?php

// ABBYY extract pictures

require_once(dirname(__FILE__) . '/config.inc.php');
require_once(dirname(__FILE__) . '/utils.php');


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
	

	//----------------------------------------------------------------------------------------------
	// Get PageIDs of pages to process
	$pages = get_jats_pages($dir, $reference_id);
	
	// Directories
	$abbyy_dir 	= $dir . '/abbyy';
	$djvu_dir 	= $dir . '/djvu';
	$html_dir	= $dir . '/html';
	
	// Where we store figures
	$figure_dir = $dir . '/figures';
	
	if (!file_exists($figure_dir))
	{
		$oldumask = umask(0); 
		mkdir($figure_dir, 0777);
		umask($oldumask);
	}
	
	// Create JATS DOM to store links to figures (and tables) that we treat as "floats"
	$doc = new DOMDocument('1.0');
	$doc->preserveWhiteSpace = false;
	$doc->formatOutput = true;	
	
	$floats_group = $doc->appendChild($doc->createElement('floats-group'));
	
	// Process each ABBYY XML file
	foreach ($pages as $page)
	{
		$xml_filename =  $abbyy_dir . '/' . $page . '.xml';
		
		// Get ABBYY XML
		$xml = file_get_contents($xml_filename);
		
		$dom= new DOMDocument;
		$dom->loadXML($xml);
		$xpath = new DOMXPath($dom);
		
		// Page dimensions
		$page_height = 0;
		$nodes = $xpath->query ('//page/@height');
		foreach($nodes as $node)
		{
			$page_height = $node->firstChild->nodeValue;
		}
		
		$page_width = 0;
		$nodes = $xpath->query ('//page/@width');
		foreach($nodes as $node)
		{
			$page_width = $node->firstChild->nodeValue;
		}
		$scale = $config['image_width']/$page_width;
		
		$pictures = array();
		
		// ABBYY may classify figures as either Picture or Table so let's grab both
		$nodes = $xpath->query ('//block[@blockType="Picture" or @blockType="Table"]');
		foreach($nodes as $node)
		{
			if ($node->hasAttributes()) 
			{ 
				$attributes = array();
				$attrs = $node->attributes; 
				
				foreach ($attrs as $i => $attr)
				{
					$attributes[$attr->name] = $attr->value; 
				}
				
				$picture = new stdclass;
				$picture->left 		= $attributes['l'];
				$picture->top 		= $attributes['t'];
				$picture->right 	= $attributes['r'];
				$picture->bottom 	= $attributes['b'];
				
				$picture->height = $picture->bottom - $picture->top;
				$picture->width = $picture->right - $picture->left;
			
				$picture->geometry = $picture->width . 'x' . $picture->height . '+' . $picture->left . '+' . ($page_height - $picture->bottom);
				
				if (!isset($pictures[$page]))
				{
					$pictures[$page] = array();
				}
				$pictures[$page][] = $picture;
			}
		}
	
		if (count($pictures) > 0)
		{	
			// extract image from DjVu		
			$djvu_filename = $djvu_dir . '/' . $page . '.djvu';
					
			foreach ($pictures as $PageID => $picture_list)
			{
				foreach ($picture_list as $p)
				{
					$figure_filename = $figure_dir . '/' . $page . '-bw-' . $p->geometry ;	
					
					$tiff_filename = $figure_filename . '.tiff';
					
					// B&W image
					$command = 'ddjvu -format=tiff -page=1' 
						. ' -segment=' . $p->geometry 
						// . ' -mode=background'
						. ' -mode=foreground'
						. ' ' . $djvu_filename 
						. ' ' . $tiff_filename;
						
					echo $command . "\n";
					system($command, $return_var);
					
					
					// Convert to PNG of correct size
					
					$png_filename = $figure_filename . '.png';
					
					$command = "convert -resize " . ($scale * $p->width) . 'x' .  ($scale * $p->height) . ' ' . $tiff_filename . ' ' .  $png_filename;
					//echo $command . "\n";
					system($command, $return_var);
					//echo $return_var . "\n";
					
					// Clean up
					unlink($tiff_filename);
					
													
					// Background
					$figure_filename = $figure_dir . '/' . $page . '-background-' . $p->geometry ;	
					
					$tiff_filename = $figure_filename . '.tiff';
		
					$command = 'ddjvu -format=tiff -page=1' 
						. ' -segment=' . $p->geometry 
						 . ' -mode=background'
						. ' ' . $djvu_filename 
						. ' ' . $tiff_filename;
						
					echo $command . "\n";
					system($command, $return_var);
					
					// Convert to JPEG of correct size
					$command = "convert -resize " . ($scale * $p->width) . 'x' .  ($scale * $p->height) . ' ' . $tiff_filename . ' ' .  $figure_filename . '.jpeg';
					//echo $command . "\n";
					system($command, $return_var);
					//echo $return_var . "\n";
					
					
					// Clean up
					unlink($tiff_filename);
					
					
					// Original (i.e., full colour scan)
					$figure_filename = $figure_dir . '/' . $page . '-' . $p->geometry ;	
					
					$tiff_filename = $figure_filename . '.tiff';
		
					$command = 'ddjvu -format=tiff -page=1' 
						. ' -segment=' . $p->geometry 
						. ' ' . $djvu_filename 
						. ' ' . $tiff_filename;
						
					echo $command . "\n";
					system($command, $return_var);
					
					$jpeg_filename = $figure_filename . '.jpeg';
					
					// Convert to JPEG of correct size and adjust color levels of image
					$command = "convert -normalize -resize " . ($scale * $p->width) . 'x' .  ($scale * $p->height) . ' ' . $tiff_filename . ' ' .  $jpeg_filename;
					//echo $command . "\n";
					system($command, $return_var);
					//echo $return_var . "\n";
					
					// Clean up
					unlink($tiff_filename);
					
		
					// JATS XML for this figure
					$fig = $floats_group->appendChild($doc->createElement('fig'));
					$caption = $fig->appendChild($doc->createElement('caption'));
					$graphic = $fig->appendChild($doc->createElement('graphic'));
					$graphic->setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
					
					// Classify image as B&W or not, and set URL appropriately
					
					if (image_is_dark($jpeg_filename))
					{
						// "colour"
						$graphic->setAttribute('xlink:href', 'figures/' . $page . '-' . $p->geometry . '.jpeg');
					}
					else
					{
						// B&W
						$graphic->setAttribute('xlink:href', 'figures/' . $page . '-bw-' . $p->geometry . '.png');
					}
					
					// Update hOCR HTML					
					$html = '<div class="ocr_float"'
						. ' id="' . $p->geometry . '"'
						. ' style="position:absolute;left:' . ($scale * $p->left) . 'px;'
						. 'top:' . ($scale * $p->top) . 'px;'
						. 'width:' . ($scale * $p->width) . 'px;'
						. 'height:' . ($scale * $p->height) . 'px;'
						. '">';
						
					$html .= '<img src="../figures/';
					if (image_is_dark($jpeg_filename))
					{
						// "colour"
						$html .= $page . '-' . $p->geometry . '.jpeg';
					}
					else
					{
						// B&W
						$html .= $page . '-bw-' . $p->geometry . '.png';
					}
					$html .= '" />';
					$html .= '</div>';
					
					// inject
					add_to_html_dom($dir . '/html/' . $page . '.html', '//div[@class="ocr_page"]', 'div[@id="2124x1508+52+2911"]', $html);
				}			
			}		
		}
		
		
	}
	 
	
	// Update JATS for article by inserting <floats-group> below <article> tag
	add_to_dom($dir, $reference_id, 'article', 'floats-group', $doc->saveXML($floats_group));
}


?>