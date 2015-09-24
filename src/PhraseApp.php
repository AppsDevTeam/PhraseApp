<?php

namespace ADT\PhraseApp;

define("LANG_DIR", APP_DIR . "/lang");


class Synchronizer {
	const GET = 1;
	const POST = 2;
	const PUT = 3;
	const DELETE = 4;
	const HOST = "https://api.phraseapp.com";
	const F_JSON = 'simple_json';

	protected $appId = null;
	protected $appDescription = null;
	protected $authToken = null;
	protected $defaultCode = null;
	protected $filesDir = LANG_DIR;
	protected $tagApp = null;
	protected $tagFile = null;
	protected $tagArray = null;
	protected $tagAux = 'SUNKINS_SVETZDRAVI_KNT_WEB_CODES';	// @todo db codes
	protected $filePrefix = 'messages';

	protected $locales = null;
	protected $defaultLocale = null;
	/** @var \Symfony\Component\Yaml\Yaml */
	protected $yamlService;
	/** @var array */
	protected $tempTranslationsArray = [];

	/**
	 * Localization keywords.
	 * @var array
	 */
	public static $ingroredKeys = [ "zero", "one", "few", "many", "other" ];


///////////////////////////////////////////////////////////////////////////////////////// DEFAULT //
////////////////////////////////////////////////////////////////////////////////////////////////////
	/**
	 * @param array $params
	 */
	public function __construct(array $params) {
		foreach ($params as $name => $value) {
			if (!in_array($name, [ 'appId', 'appDescription', 'authToken', 'defaultCode', 'filesDir', 'tagApp', 'tagFile', 'tagArray', 'filePrefix' ])) {
				throw new \Nette\InvalidArgumentException('Unknown parameters!');
			}

			$this->$name = $value;
		}

		if (!$this->appId || !$this->appDescription || !$this->authToken || !$this->defaultCode) {
			throw new \Nette\InvalidArgumentException('Required parameters are not set!');
		}

		$this->getLocales();	// fill protected $locales

		if (!$this->locales) {
			throw new \Nette\InvalidArgumentException('Something is wrong - can not find locales!');
		}

		$this->defaultLocale = $this->getDefaultLocale();

		if (!$this->defaultLocale) {
			throw new \Nette\InvalidArgumentException('Something is wrong - can not find default locale!');
		}

		$this->yamlService = new \Symfony\Component\Yaml\Yaml();
	}

///////////////////////////////////////////////////////////////////////////// GETTERS AND SETTERS //
////////////////////////////////////////////////////////////////////////////////////////////////////
	public function getTagApp() {
		return $this->tagApp;
	}

	public function setTagApp($tag) {
		$this->tagApp = $tag;
	}

	public function getTagFile() {
		return $this->tagFile;
	}

	public function setTagFile($tag) {
		$this->tagFile = $tag;
	}

	public function getTagArray() {
		return $this->tagArray;
	}

	public function setTagArray($tag) {
		$this->tagArray = $tag;
	}

///////////////////////////////////////////////////////////////////// GENERAL COMMUNICATION LAYER //
////////////////////////////////////////////////////////////////////////////////////////////////////
	/**
	 * @param $url
	 * @param int $method
	 * @param array $data
	 * @return mixed|null
	 */
	protected function send($url, $method = self::GET, $data = []) {
		$ch = curl_init();
		$projectUrl = "/api/v2/projects/" . $this->appId . $url;
		$dataString = http_build_query($data);

		curl_setopt($ch, CURLOPT_URL, self::HOST . $projectUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);	// removes problem: SSL certificate problem: certificate has expired

		$headerDescription = 'User-Agent: ' . $this->appDescription;
		$headerToken = 'Authorization: token ' . $this->authToken;

		curl_setopt($ch, CURLOPT_HTTPHEADER, [ $headerDescription, $headerToken ]);

		if ($method == self::GET) {
			curl_setopt($ch, CURLOPT_URL, self::HOST . $projectUrl . "?" . $dataString);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		} else if ($method == self::POST) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		} else if ($method == self::PUT) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		} else if ($method == self::DELETE) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
		} else {
			return null;
		}

		$result = curl_exec($ch);

		curl_close($ch);

		return $result;
	}

	/**
	 * @return bool|mixed
	 */
	public function getLocales() {
		if ($this->locales) {
			$return = $this->locales;
		} else {
			$return = false;
			$response = $this->send("/locales", self::GET);

			if ($response) {
				$obj = json_decode($response);

				if ($obj) {
					$return = $this->locales = $obj;
				}
			}
		}

		return $return;
	}

	/**
	 * @return mixed
	 */
	public function getDefaultLocale() {
		foreach ($this->locales as $locale) {
			if ($this->shortenCode($locale->code) == $this->defaultCode) {
				return $locale;
			}
		}
	}

	/**
	 * @param $localeId
	 * @param $format
	 * @param $tags
	 * @return bool
	 */
	protected function getTranslations($localeId, $tags) {
		$return = false;
		$params = [ "file_format" => self::F_JSON ];

		if ($tags) {
			/* hopefully temporary workaround */
			$array = explode(",", $tags);

			if (count($array) > 1) {
				$index = array_search($this->tagApp, $array);

				if ($index !== false) {
					unset($array[$index]);
				}
			}

			$params["tag"] = implode(",", $array);
			/* */
		}

		$response = $this->send("/locales/" . $localeId . "/download", self::GET, $params);

		if ($response) {
			$this->tempTranslationsArray = json_decode($response, true);

			if ($this->tempTranslationsArray) {
				$return = true;
			}
		}

		return $return;
	}

	/**
	 * @param $localeId
	 * @param $path
	 * @param $tags
	 * @param null $format
	 * @param bool|false $update
	 * @return bool
	 */
	protected function postFile($path, $localeId, $tags, $format = null, $update = false) {
		$return = false;

		$params = [
				"file" => new \CURLFile($path),
				"locale_id" => $localeId,
				"update_translations" => false
		];
//
//		if ($format) {
//			$params['file_format'] = $format;
//		}

		if ($tags) {
			$params['tags'] = $tags;
		}

		$response = $this->send("/uploads", self::POST, $params);

		if ($response) {
			$obj = json_decode($response);

			if ($obj) {
				if ($obj->state == "success") {
					$return = true;
				} else if ($obj->state == "processing") {
					$return = $obj->id;
				} else {
					\Tracy\Debugger::log($obj->error);
				}
			}
		}

		return $return;
	}

//////////////////////////////////////////////////////////////////////////////// HELPER FUNCTIONS //
////////////////////////////////////////////////////////////////////////////////////////////////////
	protected function shortenCode($codeLong) {
		return preg_replace("/-../", "", $codeLong);
	}

	protected function buildTags(array $tags) {
		foreach ($tags as $key => $tag) {
			if ($tag === '') {
				unset($tags[$key]);
			}
		}
//dump(implode(',', $tags));die;
		return implode(',', $tags);
	}

	/**
	 * Projde pole a ke klicovym slovum prida s nakonci, aby PhraseApp nerozbil strukturu.
	 * Pokud preklad obsahuje pouze &nbsp;, nahradi ho za prázdný řetězec.
	 */
	protected function filterTempArray() {
		foreach ($this->tempTranslationsArray as $key => $translation) {
			foreach (self::$ingroredKeys as $badKeyEnd) {
				$newKey = preg_replace('/.' . $badKeyEnd . '$/', '.' . $badKeyEnd . 's', $key);
			}

			if ($translation == "&nbsp;") {
				$translation = " ";
			}

			if ($key != $newKey) {
				unset($this->tempTranslationsArray[$key]);
			}

			$this->tempTranslationsArray[$newKey] = $translation;
		}
	}

	/**
	 * Odfiltruje backslash-underscore, ktery tam strka Symfony/YML.
	 * @param $path
	 */
	protected function filterFile($path) {
		$content = file_get_contents($path);

		$newContent = str_replace("\_", " ", $content);

		return (bool) file_put_contents($path, $newContent);
	}

	/**
	 * Projde pole překladů a zkontroluje jej zda klíč nekončí na zakazaná klíčová slova
	 * Pokud jsou překaldy v pořádku, vrátí TRUE, jinak FALSE
	 * @param array $array
	 * @return boolean
	 */
	public static function checkKeys($array) {
		foreach($array as $key => $val) {
			$keys = explode(".", "$key");

			if(in_array(end($keys), static::$ingroredKeys)) {
				return FALSE;
			}
		}

		return TRUE;
	}

////////////////////////////////////////////////////////////////////////////////// MAIN INTERFACE //
////////////////////////////////////////////////////////////////////////////////////////////////////
	/**
	 * Pull translations for all locales from PhraseApp to yml files.
	 * @param array $orTags OR-joined array of AND-joined arrays of tags.
	 * @return bool
	 */
	public function pull(array $orTags = [ null ]) {
		$ok = true;

		/* pokud nahodou preda nekdo prazdne pole v OR urovni */
		if (empty($orTags)) {
			$orTags[] = null;
		}

		/* pokud jsou AND tagy null, prida se vychozi polni tag, tag aplikace se prida v pullArray */
		foreach ($orTags as &$tags) {
			if (is_null($tags)) {
				$tags = [ $this->tagFile ];
			}
		}

		/* pres vsechny jazyky v locales */
		$arrays = $this->pullArray(null, $orTags);

		foreach ($arrays as $code => $array) {
			$path = $this->filesDir . "/" . $this->filePrefix . "." . $code . ".yml";
			$content = $this->yamlService->dump($array);

			file_put_contents($path, $content);

			$this->filterFile($path);
		}

		return $ok;
	}

	/**
	 * Pushes default language translations from yml file.
	 * @param array $tags
	 * @return bool
	 */
	public function push(array $tags = null) {
		$ok = true;
		$path = $this->filesDir . "/" . $this->filePrefix . "." . $this->defaultCode . ".yml";

		if (!file_exists($path)) {
			\Tracy\Debugger::log("Can not open file: '" . $path . "'");

			$ok = false;
		}

		if ($ok) {
			if (is_null($tags)) {
				$tags = [ $this->tagFile ];
			}

			$tags[] = $this->tagApp;

			$this->filterFile($path);

			$ok = $this->postFile($path, $this->defaultLocale->id, $this->buildTags($tags));
		}

		return $ok;
	}

	/**
	 * Checks success of asynchronous file upload.
	 * @param $uploadId
	 * @return bool
	 */
	public function checkPush($uploadId) {
		$ok = false;
		$response = $this->send("/uploads/" . $uploadId);

		if ($response) {
			$obj = json_decode($response);

			if ($obj) {
				if ($obj->state == "success") {
					$ok = true;
				} else {
					\Tracy\Debugger::log($obj->error);
				}
			}
		}

		return $ok;
	}

	/**
	 * Pulls array of translations to array.
	 * @param array $orTags OR-joined array of AND-joined arrays of tags.
	 * @param array $codes
	 * @return bool|\Nette\Utils\ArrayHash
	 */
	public function pullArray(array $codes = null, array $orTags = [ null ]) {
		$ok = true;
		$arrays = new \Nette\Utils\ArrayHash();

		/* pokud nahodou preda nekdo prazdne pole v OR urovni */
		if (empty($orTags)) {
			$orTags[] = null;
		}

		/* pokud jsou AND tagy null, prida se vychozi polni tag, vzdy se prida tag aplikace */
		foreach ($orTags as &$tags) {
			if (is_null($tags)) {
				$tags = [ $this->tagArray ];
			} else if (!is_array($tags)) {
				throw new \Nette\InvalidArgumentException('Parameter $orTags must contain arrays!');
			}

			$tags[] = $this->tagApp;
		}

		/* pres vsechny jazyky v codes, nebo pres vsechny v locales, kdyz je codes null */
		foreach ($this->locales as $locale) {
			$code = $this->shortenCode($locale->code);

			if (is_null($codes) || in_array($code, $codes)) {
				if (!isset($arrays[$code])) {
					$arrays[$code] = [];
				}

				/* pres vsechny OR tagy */
				foreach($orTags as $tags) {
					if (!$this->getTranslations($locale->id, $this->buildTags($tags))) {
						$ok = false;

						continue;
					}

					$this->filterTempArray();

					$arrays[$code] += $this->tempTranslationsArray;
				}
			}
		}

		if ($ok) {
			$ok = $arrays;
		}

		return $ok;
	}

	/**
	 * Pushes default language translations from array.
	 * @param $array
	 * @param array $tags Array of tags joined by AND.
	 * @return bool
	 */
	public function pushArray($array, array $tags = null) {
		$tempPath = $this->filesDir . '/array.yml';
		$content = $this->yamlService->dump($array);

		file_put_contents($tempPath, $content);

		if (is_null($tags)) {
			$tags = [ $this->tagArray ];
		}

		$tags[] = $this->tagApp;

		$this->filterFile($tempPath);

		$ok = $this->postFile($tempPath, $this->defaultLocale->id, $this->buildTags($tags));

		unlink($tempPath);

		return $ok;
	}
}