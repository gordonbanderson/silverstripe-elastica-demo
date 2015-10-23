<?php


/**
 * Defines and refreshes the elastic search index.
 */
class FixPhotoDescriptionTask extends \BuildTask {

	protected $title = 'Tidy up photographic descriptions that are of a standard format';

	protected $description = 'Tidy up photographic descriptions';


	/*
	Target authors to fix with this query

	SELECT COUNT(fa.ID) as NumberOfPics, PhotographerID,DisplayName,RealName,Username,NSID
	FROM FlickrPhoto fp
	INNER JOIN FlickrAuthor fa
	ON fa.ID = fp.PhotographerID
	GROUP BY PhotographerID
	ORDER BY NumberOfPics
	;


	 */


	public function run($request) {
		$message = function ($content) {
			print(\Director::is_cli() ? "$content\n" : "<p>$content</p>");
		};

		$authorID = $request->getVar('AuthorID');

		$fps = FlickrPhoto::get()->filter(array('PhotographerID' => $authorID));

		foreach ($fps as $fp) {
			echo "+++++++++++++++++++++++++++++\n";
			echo $fp->Title."\n";
			if ($fp->OriginalDescription == null) {
				$methodName = 'fix'.$authorID;
				echo "---------- ID {$fp->ID} {$fp->Title} ------------\n";
				echo "BEFORE:\n{$fp->Description}\n\n\n";
				$fixedDescription = $this->$methodName($fp->Description);
				$fp->IndexingOff = true;
				$fp->OriginalDescription = $fp->Description;
				$fp->Description = $fixedDescription;
				$fp->write();

				echo "\n\nTIDIED:\n$fixedDescription";
				echo "\n--------------------\n";
			} else {
				echo "Already parsed";
			}
		}
	}


	private function fix23($desc) {
		$lines = explode("\n", $desc);
		return $lines[0];
	}

	/* National Archives UK */
	public function fix8($desc) {
		$lines = explode("\n", $desc);
		$result = '';
		foreach ($lines as $line) {
			if (substr($line, 0,19) == '<b>Description:</b>') {
				$newLine =  "\n\n<p>".str_replace('<b>Description:</b>', '', $line."</p>");
				$newLine = trim($newLine);
				echo "\tNEW LINE:$newLine\n";
				if ($newLine != 'Description:') {
					$result .= $newLine;
				}
			}

			if (substr($line, 0,16) == '<b>Location:</b>') {
				$result .= "\n\n<p>".str_replace('<b>Location:</b>', '', $line)."</p>";
			}
		}

		$result = str_replace('Description:', '', $result);

		return $result;
	}

	/* San Diego Air & Space Museum Archives */
	public function fix14($desc) {
		$lines = explode("\n", $desc);
		$result = array();

		$ditchIfLineContains = array('PUBLIC COMMONS.SOURCE INSTITUTION:','Repository:',
			'Catalogue:', 'Collection:','Page:','Album','Picture on Page',
			'COMMONS.SOURCE INSTITUTION:','Catalog #:','From the Collection of Charles M. Daniels',
			'<b>Catalog #:</b>','<b>Repository:</b>','Ray Wagner Collection Photo','SDASM.CATALOG:'
			);

		$keepAsPara = array('<b>Manufacturer:</b>', '<b>Designation:</b>',
			'<b>Official Nickname:</b>', '<b>Notes:</b>', 'Title:', 'Corporation Name:',
			'Additional Information:', 'Designation:', 'Tags:','SDASM.TITLE:','SDASM.DATE:',
			'SDASM.ADDITIONAL INFORMATION:', 'SDASM.TAGS:');
/*
SDASM.CATALOG: Balchen_000061
SDASM.TITLE: Balchen and Skies
SDASM.DATE: 1926
SDASM.ADDITIONAL INFORMATION: Balchen rebuilt and installed new skis for the Byrd aircraft in Spitsbergen, enabling Byrd to successfully take off for his attempt to fly to the North Pole. Balchen is seen on the left with an assistant at the front of the ski. A Norwegian naval officer stands to the right, 1926.
SDASM.COLLECTION: Bernt Balchen Collection
SDASM.TAGS: Balchen and Skies ,,


 */
		foreach ($lines as $line) {
			//If a line contains a negative phrase skip it and jog on to the next one
			foreach ($ditchIfLineContains as $potentialMatch) {
				$len = strlen($potentialMatch);
				$start = substr($line, 0,$len);
				if ($start == $potentialMatch) {
					continue 2; // exit this loop and the one for this line, ie skip it
				}
			}

			// remove prefix and keep line as a paragraph
			foreach ($keepAsPara as $prefix) {
				$len = strlen($prefix);
				$start = substr($line, 0,$len);
				if ($start == $prefix) {
					$line = str_replace($prefix, '', $line);
					$line = '<p>'.trim($line).'</p>';
				}
			}

			$result[] .= $line;
		}


		$result = implode("\n", $result);
		return $result;
	}

	/* SMU Central University Libraries */
	private function fix4($desc) {
		$lines = explode("\n", $desc);
		$result = '';
		foreach ($lines as $line) {
			if (substr($line, 0,6) == 'Place:') {
				$result = str_replace('Place: ', '', $line);
			}
		}
		return $result;
	}

	/*
	British Library
	 */
	private function fix3($desc) {
		// description is fluff with addition of title
		return '';
	}

	/*
	Internet book archive, this one is nicely parseable
	 */
	private function fix2($desc) {
		$textBeforeMarker = strpos($desc,'<b>Text Appearing Before Image:</b>');
		$textAfterMarker = strpos($desc,'<b>Text Appearing After Image:</b>');
		$notesMarker = strpos($desc,'<b>Note About Images</b>');

		echo "MARKERS: $textBeforeMarker, $textAfterMarker, $notesMarker\n";
		$textBefore = substr($desc, $textBeforeMarker, $textAfterMarker - $textBeforeMarker);
		$textBefore = str_replace('<b>Text Appearing Before Image:</b>','', $textBefore);
		$textBefore = trim($textBefore);

		$textAfter = substr($desc, $textAfterMarker, $notesMarker - $textAfterMarker);
		$textAfter = trim($textAfter);

		$result = $textBefore."\n".$textAfter;
		return $result;
	}


	/*
	National Library of NZ Commons
	 */
	private function fix1($desc) {
		$result = array();
		$lines = explode("\n", $desc);
		$skip = array('Quantity:','Physical Description:','Inscriptions:','Reference Number:','Photographer:', 'Reference number:', 'Provenance:',
			'Reference No.');

		//Ditch a line if it contains this text
		$ditchIfLineContains = array('Find out more about this image');

		//Ditch the line if it's an exact match
		$ditchIfExactMatch = array('Original negative','Glass negative','Photographic Archive, Alexander Turnbull Library','Silver gelatin print',
			'Film negative','Original negative','Single photograph','Gelatin dry plate negative','Dry plate glass negative',
			'Photographic Archive, Alexander Turnbull Library, National Library of New Zealand','Original print','Unidentified photographer',
			'Stereoscopic glass 1/2 plate negative','Photographer William Williams.',
			'Panoramic negative','Price Collection, Photographic Archive, Alexander Turnbull Library','Stereographic dry plate glass negative',
			'Photographer William Archer Price','Price Collection, Alexander Turnbull Library, National Library of New Zealand',
			"Royal New Zealand Returned and Services' Association Collection, Alexander Turnbull Library, National Library of New Zealand",
			'Printed Ephemera Collection, Alexander Turnbull Library','Photolithographs','Panoramic nitrate negative','Toned silver gelatin print',
			'Albumen print','Gelatin silver print','Silver gelatin print,'
		);

		foreach ($lines as $line) {
			$line = str_replace('Description: ', '', $line);
			//Skip find out more URL links
			if (strpos($line, 'Find out more about this image') > 0) {
				continue ;
			}

			//If a line contains a negative phrase skip it and jog on to the next one
			foreach ($ditchIfLineContains as $potentialMatch) {
				if (strpos($line, $potentialMatch) > 0) {
					continue 2;
				}
			}

			//Skip exact matches
			foreach ($ditchIfExactMatch as $potentialMatch) {
				if ($potentialMatch == trim($line)) {
					continue 2;
				}
			}

			foreach ($ditchIfLineContains as $potentialMatch) {
				if (strpos($line, $potentialMatch) > 0) {
					continue 2;
				}
			}

			$foundPrefixToDelete = false;
			foreach ($skip as $prefix) {
				$len = strlen($prefix);
				if (strpos(trim($line), $prefix) === 0) {
					$foundPrefixToDelete = true;
					break;
				}
			}
			$splits = explode(':', $line);


			$refpos = strpos($line, 'Ref:');
			if ($refpos > 0) {
				$line = substr($line, 0,$refpos);
			}

			//echo "SAVING LINE:*$line*\n";

			if (!$foundPrefixToDelete) {
				array_push($result,$line);
			}


		}
		return implode("\n", $result);
	}

}
