<?php
	class FlickrAuthor extends DataObject {
		private static $db = array(
			'PathAlias' => 'Varchar',
			'DisplayName' => 'Varchar'
		);

		private static $has_many = array('FlickrPhotos' => 'FlickrPhoto');


		private static $summary_fields = array(
			'PathAlias' => 'URL',
			'DisplayName' => 'Display Name'
		);


		/**
		 * A search is made of the path alias during flickr set import
		 */
		private static $indexes = array(
			'PathAlias' => true
		);
	}
