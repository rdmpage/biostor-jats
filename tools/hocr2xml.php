<?php

// DjVu to HTML
require_once (dirname(__FILE__) . '/config.inc.php');
require_once (dirname(__FILE__) . '/djvu_xml.php');
require_once (dirname(__FILE__) . '/utils.php');
require_once(dirname(dirname(__FILE__)) . '/fpdf17/fpdf.php');



//--------------------------------------------------------------------------------------------------
/*
$dir = '';
if ($argc < 2)
{
	echo "Usage: " . str_replace(dirname(__FILE__) . '/', '', __FILE__) . " <directory> \n";
	exit(1);
}
else
{
	$dir = $argv[1];*/
{
	
	$dir = 'examples/65706';
	
	if (preg_match('/(?<path>(.*))\/(?<id>\d+)$/', $dir, $m))
	{
		$reference_id = $m['id'];
	}
	else
	{
		exit();
	}

	$pages = get_jats_pages($dir, $reference_id);
	
	$html_dir = $dir . '/html';

	
	// pages
	$num_pages = count($pages);
	for ($k = 0; $k < $num_pages; $k++)
	{
		
		$html_filename = $html_dir . '/' . $pages[$k] . '.html';	
		$html = file_get_contents($html_filename);
		
		//echo $html;
		
		$dom= new DOMDocument;
		$dom->loadHTML($html);
		$xpath = new DOMXPath($dom);
				
		$paragraphs = $xpath->query ('//div[@class="ocr_par"]');
		foreach($paragraphs as $p)
		{
			echo '<p>';
			
			$text = '';
			
			$lines = $xpath->query ('div[@class="ocr_line"]', $p);
			foreach($lines as $line)
			{
				$t = $line->firstChild->nodeValue;
				$t = preg_replace('/-\s*$/u', '&shy;', $t);
				$text .= $t;
			}
			
			$text = htmlspecialchars_decode($text);	
			$text = utf8_decode($text);
			echo $text;
			
			echo '</p>';
		}
	}	
}	
	
?>