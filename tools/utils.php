<?php

// Utils

//--------------------------------------------------------------------------------------------------
// classify an image as "dark" or "light"
function image_is_dark($image_filename)
{
	$dark = false;
	
	$rgb_threshold = 160;
	$percent_threshold = 10;
	
	$command = "convert " . $image_filename . ' -colors 4 -format "%c" histogram:info:-';
	//echo $command . "\n";
	$output = array();
	$return_var = 0;
	exec($command, $output, $return_var);
					
	//print_r($output);
	
	$sum = 0;
	
	$colours = array();
	foreach ($output as $line)
	{	
		if (preg_match('/(?<count>\d+):\s+\((.*)\)\s+#\w+\s+s(?<rgb>rgb\((?<red>\d+),(?<green>\d+),(?<blue>\d+)\))/', $line, $m))
		{
			//print_r($m);
			
			$colour = new stdclass;
			$colour->count = $m['count'];
			$colour->rgb = $m['rgb'];
			$colour->red = $m['red'];
			$colour->green = $m['green'];
			$colour->blue = $m['blue'];
			
			$sum += $colour->count;
			
			$colours[] = $colour;
		}
	}
	
	// analyse for darkness
	
	$percent_dark = 0;
	foreach ($colours as $colour)
	{
		$mean = ($colour->red + $colour->green + $colour->blue)/3.0;
		if ($mean < $rgb_threshold)
		{
			$percent_dark += round(100*$colour->count/$sum);
		}
	}
	
	//echo "percent_dark = $percent_dark\n";
	
	return ($percent_dark >= $percent_threshold);
}

//--------------------------------------------------------------------------------------------------
// Get PageIDs for pages in JATS XML
function get_jats_pages($basedir, $reference_id)
{
	
	$xml_filename = $basedir . '/' . $reference_id . '.xml';
	

	// Get XML
	$xml = file_get_contents($xml_filename);
	
	$dom= new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	$pages = array();
	
	$nodes = $xpath->query ('//body/supplementary-material[@content-type="scanned-pages"]/graphic/@xlink:role');
	foreach($nodes as $node)
	{
		$id = $node->firstChild->nodeValue;;
		$pages[] = $id;
	}
	
	return $pages;
}

//--------------------------------------------------------------------------------------------------
// Get PageIDs for pages in JATS XML
function get_jats_page_images($basedir, $reference_id)
{
	$xml_filename = $basedir . '/' . $reference_id . '.xml';	

	// Get XML
	$xml = file_get_contents($xml_filename);
			
	$dom= new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	$images = array();
	
	$nodes = $xpath->query ('//body/supplementary-material[@content-type="scanned-pages"]/graphic/@xlink:href');
	foreach($nodes as $node)
	{
		$href = $node->firstChild->nodeValue;;
		$images[] = $href;
	}
	
	return $images;
}

//--------------------------------------------------------------------------------------------------
// Add some XML to the JATS DOM
function add_to_dom($basedir, $reference_id, $where_to_add, $root, $xml_to_add)
{	
	$xml_filename = $basedir . '/' . $reference_id . '.xml';
	

	// Get XML
	$xml = file_get_contents($xml_filename);
	
		
	$dom= new DOMDocument;
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;	
	
	$dom->loadXML($xml);
	
	// replacement 
	$replacement  = $dom->createDocumentFragment();
	$replacement->appendXML($xml_to_add);
	
	$xp = new DOMXPath($dom);
	$oldNode = $xp->query('//' . $where_to_add . '/' . $root)->item(0);
	if ($oldNode) 
	{
		// replace
		$oldNode->parentNode->replaceChild($replacement, $oldNode);
	}
	else
	{
		// add
		$parent = $xp->query('//' . $where_to_add)->item(0);
		$parent->appendChild($replacement);
	}
	
	
	/*
	$nodes = $dom->getElementsByTagName($where_to_add);
	if ($nodes->length != 0) 
	{ 
		foreach ($nodes as $n)
		{		
			$f = $dom->createDocumentFragment();
			$f->appendXML($xml_to_add);
			$n->appendChild($f);
		}
	}
	*/
	
	file_put_contents($xml_filename,  $dom->saveXML());

}

//--------------------------------------------------------------------------------------------------
// Inject some HTML into the hOCR file
function add_to_html_dom($html_filename, $where_to_add, $root, $html_to_add)
{	
	

	// Get HTML
	$html = file_get_contents($html_filename);
	
		
	$dom= new DOMDocument;
	
	$dom->loadHTML($html);
	
	// replacement 
	$replacement  = $dom->createDocumentFragment();
	$replacement->appendXML($html_to_add);
	
	$xp = new DOMXPath($dom);
	$oldNode = $xp->query('//' . $where_to_add . '/' . $root)->item(0);
	if ($oldNode) 
	{
		// replace
		$oldNode->parentNode->replaceChild($replacement, $oldNode);
	}
	else
	{
		// add
		$parent = $xp->query('//' . $where_to_add)->item(0);
		$parent->appendChild($replacement);
	}
	
	//echo $dom->saveHTML();
		
	file_put_contents($html_filename,  $dom->saveHTML());

}



?>