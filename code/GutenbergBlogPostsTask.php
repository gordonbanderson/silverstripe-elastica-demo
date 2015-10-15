<?php

/**
 * Defines and refreshes the elastic search index.
 */
class GutenbergBlogPostsTask extends \BuildTask {

	protected $title = 'Gutenberg Blog';

	protected $description = 'Download gutenberg books and convert the text into blog posts';

	/**
	 * @var ElasticaService
	 */
	private $service;


	public function run($request) {

		Session::set("loggedInAs",1);

		$canAccess = ( Director::isDev() || Director::is_cli() || Permission::check( "ADMIN" ) );
		if ( !$canAccess ) {
			return Security::permissionFailure( $this );
		}

		$startTime = microtime(true);
		$message = function ($content) {
			print(\Director::is_cli() ? "$content\n" : "<p>$content</p>");
		};

		$blog = new Blog();
		$blog->Title = 'Gutenberg';
		$blog->write();
		$blog->publish( "Stage", "Live" );

		$translatedBlogs = array();
		$translatedBlogs[$blog->Locale] = $blog;

		$books = Config::inst()->get(get_class($this), 'Books');
		foreach ($books as $bookarray) {
			$nDefaultLocaleBlogPosts = GutenbergBookExtract::get()->filter('Locale', i18n::default_locale())->count();
			$book = $bookarray['Book'];
			$message("Importing ".$book['Title'],"\n\n");

			$title = $book['Title'];
			$locale = i18n::default_locale();
			if (isset($book['Locale'])) {
				$locale = $book['Locale'];
				//Translatable::set_current_locale($locale);
				//i18n::set_locale($locale);
				echo "++++ LOCALE SET TO $locale ++++\n\n";

				if (!isset($translatedBlogs[$locale])) {
					if (!$blog->hasTranslation($locale)) {
						$tblog = $blog->createTranslation($locale);
						$tblog->write();
						$translatedBlogs[$locale] = $tblog;
					}

				}
			}


			$slug = strtolower($title);
			$slug = str_replace(' ', '-', $slug);
			$filename = "/tmp/{$slug}.txt";
			$url = $book['URL'];
			echo $filename."\n";
			if (!file_exists($filename)) {
				echo '+++++ downloading +++++';
				$this->download_remote_file_with_curl($url, $filename);
			}


			$parsing = false;
			$handle = fopen($filename, "r");
			$paras = array();
			$para = '';
			$ctr = 1;
			if ($handle) {
			    while (($line = fgets($handle)) !== false) {
			    	$line = trim($line);

			    	if ($this->contains('START OF THIS PROJECT GUTENBERG', $line) ||
			    		$this->contains('START OF THE PROJECT GUTENBERG', $line)) {
			    		echo "\t PARSING\n";
			    		$parsing = true;
			    		continue;
			    	} else if ($this->contains('END OF THIS PROJECT GUTENBERG', $line) ||
			    		$this->contains('END OF THE PROJECT GUTENBERG', $line)) {
			    		echo "\t STOP PARSING\n";
			    		$parsing = false;
			    		continue;
			    	}

			        if ($parsing) {
			        	if (strlen($line) === 0) {
			        		if (strlen($para) > 0) {
			        			$para = '<p>'.$para.'</p>';
			        			array_push($paras, $para);
			        			$para = '';


			        			if (mt_rand(1,10) === 1 && (sizeof($paras) >= 2)) {
			        				echo "BLOG POST\n===========\n";
			        				$text = implode("\n", $paras);
			        				$extractTitle = array_shift($paras);
			        				//Attempt to grad just the first sentence after removing para tags
			        				$extractTitle = str_replace('[', '', $extractTitle);
			        				$extractTitle = str_replace(']', '', $extractTitle);
			        				$extractTitle = str_replace('<p>', '', $extractTitle);
			        				$extractTitle = str_replace('</p>', '', $extractTitle);
			        				$extractTitle = trim($extractTitle,'');

			        				$splits = explode('. ', $extractTitle);
			        				$extractTitle = ucwords($splits[0]);
			        				$post = null;
			        				if ($locale != i18n::default_locale()) {
			        					$offset = rand(0,$nDefaultLocaleBlogPosts-1);
			        					echo "N POSTS = ".$nDefaultLocaleBlogPosts.", offset $offset \n";
			        					$randomPost = BlogPost::get()->limit(1,$offset)->first();

			        					echo "RAND POST: ".$randomPost."\n";
			        					echo "MEMBER ID: ".Member::currentUserID()."\n";



			        					if (!$randomPost->hasTranslation($locale)) {
			        						$post = $randomPost->createTranslation($locale);
			        					} else {
			        						echo "**** POST ALREADY TRANSLATED";
			        						continue;
			        					}

			        				}
			        				$post = new GutenbergBookExtract();
			        				$tblog = $translatedBlogs[$locale];
			        				$post->ParentID = $tblog->ID;
			        				$post->Source = $title;
			        				$post->Title = $extractTitle;
			        				$ctr++;
			        				$post->Content = trim( $text);
			        				$past = time()-mt_rand(0, 3600*24*730);
			        				$date = date('Y-m-d', $past);

			        				$post->PublishDate = $date;
			        				$post->Created = $date;
			        				$post->LastEdited = $date;
			        				$post->Locale = $locale;
			        				$post->write();
			        				$post->publish( "Stage", "Live" );
			        				$paras = array();
			        			}
			        		}
			        	} else {
			        		$para = $para."\n".$line;
			        		echo '.';

			        	}
			        }


			    }

			    fclose($handle);

			    $message("/Importing ".$book['Title']."\n\n\n\n");
			} else {
			    // error opening the file.
			}
		}
	}

	private function contains($needle, $haystack) {
	    return strpos($haystack, $needle) !== false;
	}


	private function randomDate($start_date, $end_date) {
	    // Convert to timetamps
	    $min = strtotime($start_date);
	    $max = strtotime($end_date);

	    // Generate random number using above bounds
	    $val = rand($min, $max);

	    // Convert back to desired date format
	    return date('Y-m-d H:i:s', $val);
	}


	private function download_remote_file_with_curl($file_url, $save_to)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 0);
		curl_setopt($ch,CURLOPT_URL,$file_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$file_content = curl_exec($ch);
		curl_close($ch);

		$downloaded_file = fopen($save_to, 'w');
		fwrite($downloaded_file, $file_content);
		fclose($downloaded_file);

	}

}
