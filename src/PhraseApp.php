<?php

namespace ADT;

class PhraseApp {
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

	protected $locales = null;
	protected $defaultLocale = null;

	public static $forbiddenKeys = [ "zero", "one", "few", "many", "other" ];
	public static $substitution = "&nbsp;";

	///////////////////////////////////////////////////////////////////////////////////////// DEFAULT //
	////////////////////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * @param array $params
	 */
	public function __construct(array $params) {
		foreach ($params as $name => $value) {
			if (!in_array($name, [ 'appId', 'appDescription', 'authToken', 'defaultCode' ])) {
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

	//////////////////////////////////////////////////////////////////////////////// HELPER FUNCTIONS //
	////////////////////////////////////////////////////////////////////////////////////////////////////
	
	protected function shortenCode($codeLong) {
		return preg_replace("/-../", "", $codeLong);
	}

	/**
	 * Projde pole překladů a zkontroluje jej zda klíč nekončí na zakazaná klíčová slova
	 * Pokud jsou překlady v pořádku, vrátí FALSE, jinak název klíče
	 * @param array $array
	 * @return boolean
	 */
	public static function endsWithForbiddenWord($array) {
		foreach($array as $key => $val) {
			$keys = explode(".", "$key");

			if(in_array(end($keys), static::$forbiddenKeys)) {
				return $key;
			}
		}

		return FALSE;
	}
	
	protected function replaceNbspForSpace($translations)
	{
		foreach ($translations as $key => $translation) {
			if ($translation == self::$substitution) {
				$translations[$key] = " ";
			}
		}
		
		return $translations;
	}

	////////////////////////////////////////////////////////////////////////////////// MAIN INTERFACE //
	////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Pulls array of translations to array.
	 * @param array $codes
	 * @param string $tag
	 * @return bool|\Nette\Utils\ArrayHash
	 */
	public function pullLocales($tag = NULL) 
	{
		$locales = [];

		$params = [ 
			"file_format" => self::F_JSON,
		];

		if ($tag) {
			$params['tag'] = $tag;
		}
		
		/* pres vsechny jazyky v locales */
		foreach ($this->locales as $locale) {
			if ($response = $this->send("/locales/" . $locale->id . "/download", self::GET, $params)) {
				$locales[$this->shortenCode($locale->code)] = $this->replaceNbspForSpace(json_decode($response, TRUE));
			} else {
				throw new \Exception('Error');
			}
		}

		return $locales;
	}
	
	public function pull($lastUpdate, $tag = NULL)
	{		
		$locales = [];

		$lastUpdate = new \DateTime($lastUpdate);
		$lastUpdate->setTimezone(new \DateTimeZone('UTC'));
		
		$params = [
			'q' => 'updated_at:>=' . $lastUpdate->format('c') . ($tag ? ' tags:' . $tag : '')
		];
			
		if ($response = $this->send('/translations', self::GET, $params)) {
			foreach (json_decode($response) as $translation) {
				$locales[$this->shortenCode($translation->locale->code)][$translation->key->name] = $translation->content;	
			}
		}
		
		foreach ($locales as $locale => $translations) {
			$locales[$locale] = $this->replaceNbspForSpace($translations);
		}
		
		return $locales;
	}
	
	/**
	 * @param $translations
	 * @param $tags
	 * @param null $format
	 * @param bool|false $update
	 * @return bool
	 */
	public function push(array $translations, array $tags = []) 
	{	
		if ($key = self::endsWithForbiddenWord($translations)) {
			throw new \Exception('Key ' . $key . ' ends with a forbidden word.');
		}
		
		foreach ($translations as $key => $translation) {
			if (empty(trim($translation))) {
				$translations[$key] = self::$substitution;
			}
		}
		
		$path = sys_get_temp_dir() . '/translations.yml';
		file_put_contents($path, json_encode($translations));
		
		$params = [
				"file" => new \CURLFile($path),
				"file_format" => self::F_JSON,
				"locale_id" => $this->defaultLocale->id,
				"update_translations" => false
		];

		if ($tags) {
			$params['tags'] = implode(',', $tags);
		}

		$response = $this->send("/uploads", self::POST, $params);

		if ($response) {
			$obj = json_decode($response);
			
			if ($obj) {
				if ($obj->state == "success") {
					return TRUE;
				} else if ($obj->state == "processing") {
					return $obj->id;
				} else {
					throw new \Exception($obj->error);
				}
			}
		}

		unlink($path);
		
		return false;
	}	
}
