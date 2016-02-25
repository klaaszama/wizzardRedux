<?php

/*
Internal test to see if the No-Intro pages can be traversed reasonably

Requires
	auto		Set to 1 if a new no-intro mapping needs to be created
*/

// Create a name to field mapping for each of the findable fields
$field_mapping = array (
		"Size:" => "size",
		"CRC32:" => "crc",
		"MD5:" => "md5",
		"SHA-1:" => "sha1",
		"Decrypted CRC32:" => "crc",
		"Decrypted MD5:" => "md5",
		"Decrypted SHA-1:" => "sha1",
		"Directory:" => "dir",
		"NFO File:" => "nfo",
		"Group:" => "group",
		"Released" => "date",
		"Section:" => "section",
);

ini_set('max_execution_time', 6000); // Set the execution time higher because DATs can be big

// auto means create the mapping
if (isset($_GET["auto"]) && $_GET["auto"] == "1")
{
	$query = file("http://datomatic.no-intro.org/?page=download");
	
	$handle = fopen("../css/nointro.php", "w");
	fwrite($handle, "<?php\n\n".
			"/*\nAuto-generated by parsenointro.php\n*/\n\n".
			"// Mapping for No-Intro systems to ids\n".
			"\$no_intro_ids = array (\n");
	foreach ($query as $line)
	{
		if (strpos($line, "index.php?page=search&s="))
		{
			$line = trim($line);
			preg_match("/<b><a href=\"index\.php\?page=search&s=([0-9]+).*>(.*)<\/a><\/b>/", $line, $xml);
			fwrite($handle, "\t'".$xml[2]."' => ".$xml[1].",\n");
		}
	}
	fwrite($handle, ")\n\n?>");
	fclose($handle);
}
// Try and get rom information direct from the no-intro pages. (Rollback?)
else
{
	echo "<a href='page=parsenointro&auto=1'>Auto-generate no-intro name to system mapping</a><br/><br/>\n";
	
	$gameid = 1; $maxid = 5;
	$errorpage = false;
	$roms = array();
	while (!$errorpage)
	{
		// Retrieve the page information
		$query = get_data("http://datomatic.no-intro.org/index.php?page=show_record&s=28&n=".str_pad($gameid, 4, "0", STR_PAD_LEFT));
				
		// The error page case, it means time to stop the cycle
		// This could result in too many page request too... not sure though
		if ($query == "" || strpos($query, "I am too busy for this!") || $gameid > $maxid)
		{
			$errorpage = true;
			break;
		}
		
		// Replace tabs and nbsp by blank string (this make sure that spaces in names aren't removed)
		$query = str_replace("    ", "", $query);
		$query = str_replace("\t", "", $query);
		$query = str_replace("&nbsp;", "", $query);
		
		// Split the page and only take the stuff under the header
		$query = explode("</header>", $query);
		$query = $query[1];
		
		// Split the page and only take the stuff before the sidebar and footer
		$query = explode("</article>", $query);
		$query = $query[0];
		
		// Remove all tags from the page to make it easier to parse
		$query = strip_tags($query);
		
		// Get rid of all multiple newline sets (has to be done repeatedly because of how searching works)
		$query = str_replace("\r\n", "\n", $query);
		for ($i = 0; $i < 10; $i++)
		{
			$query = str_replace("\n\n", "\n", $query);
		}
		$query = str_replace("\n", "<br/>\n", $query);
		
		// Read the processed page into an array and get rid of the first unnecesary items
		$query = explode("\n", $query);
		unset($query[0]); unset($query[1]);
		
		$rom = array(); // Individual ROM information
		$next = ""; // What the key for the next value is
		foreach ($query as $line)
		{
			$line = strip_tags($line);
			
			// The first line that doesn't mention a trusted dump or verificaiton is the name of the ROM
			if ($line != "" && $rom["name"] == "")
			{
				echo "Name: ".$line."<br/>\n";
				$rom["name"] = $line;
			}
			// Check the key half of all of split-line fields that we know of
			elseif ($next == "")
			{
				foreach (array_keys($field_mapping) as $key)
				{
					if ($line == $key)
					{
						// Check if the key is already set in the rom array
						if (isset($rom[$key]))
						{
							// If it is, push the current rom information to the output
							echo "Pushing rom";
							array_push($roms, $rom);
							
							// We want to blank out everything except size
							foreach ($field_mapping as $val)
							{
								if ($val != "size")
								{
									$rom[$val] = "";
								}
							}
						}
						
						echo $key." ";
						$next = $key;
					}
				}
			}			
			// Check the value half of all split-line fields that we know of
			else
			{
				if (key_exists($next, $field_mapping))
				{
					echo $line."<br/>\n";
					$rom[$field_mapping[$next]] == $line;
				}
				$next = "";
			}
		}
		
		$gameid++;
		echo "<br/>";
	}
	
	echo "Error page hit or ran out of numbers.<br/>";
	var_dump($roms);
}

//https://davidwalsh.name/curl-download
/* gets the data from a URL */
function get_data($url)
{
	$ch = curl_init();
	$timeout = 5;
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}


?>