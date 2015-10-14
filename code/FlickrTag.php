<?php
class FlickrTag extends DataObject {

	static $db = array(
		'Value' => 'Varchar',
		'FlickrID' => 'Varchar',
		'RawValue' => 'HTMLText'
	);

	static $display_fields = array(
		'RawValue'
	);


	static $searchable_fields = array(
		'RawValue'
	);

	static $summary_fields = array(
		'Value',
		'RawValue',
		'FlickrID'
	);

	static $belongs_many_many = array(
		'FlickrPhotos' => 'FlickrPhoto'
	);


	public function NormaliseCount($c) {
		return log(doubleval($c),2);
	}


	// this is required so the grid field autocompleter returns readable entries after searching
	function Title() {
		return $this->RawValue;
	}


	/*
	Static helper
	*/
	public static function CreateOrFindTags($csv) {
		$result = new ArrayList();

		if (trim($csv) == '') {
			return $result; // ie empty array
		}

		$tags = explode(',', $csv);
		foreach($tags as $tagName) {
			$tagName = trim($tagName);
			if (!$tagName) {
				continue;
			}
			$ftag = DataList::create('FlickrTag')->where("Value='".strtolower($tagName)."'")->first();
			if (!$ftag) {
				$ftag = FlickrTag::create();
				$ftag->RawValue = $tagName;
				$ftag->Value  = strtolower($tagName);
				$ftag->write();
			}

			$result->add($ftag);

		}

		return $result;
	}

}
