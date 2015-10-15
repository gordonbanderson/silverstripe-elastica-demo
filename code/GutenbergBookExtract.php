<?php

class GutenbergBookExtract extends BlogPost {
	// This is not indexed but shown in the results
	private static $db = array('Source' => 'Varchar');
}

class GutenbergBookExtract_Controller extends BlogPost_Controller {

}
