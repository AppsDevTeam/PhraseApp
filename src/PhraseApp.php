<?php

namespace ADT;

define("LANG_DIR", APP_DIR . "/lang");


class PhraseApp {
	const GET = 1;
	const POST = 2;
	const PUT = 3;
	const DELETE = 4;
	const HOST = "https://phraseapp.com";
	const LOGIN = "jakub@appsdevteam.com";
	const PASSWORD = "3tryQS6y";


	protected function send($url, $method = self::GET, $data = array()) {
		$ch = curl_init();
		$dataString = http_build_query($data);

		curl_setopt($ch, CURLOPT_URL, self::HOST . $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		if ($method == self::GET) {
			curl_setopt($ch, CURLOPT_URL, self::HOST . $url . "?" . $dataString);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
		} else if ($method == self::POST) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
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

	protected function authenticate($email, $password) {
		$response = $this->send("/api/v1/sessions", self::POST, array("email" => $email, "password" => $password));
		$return = false;

		if ($response) {
			$obj = json_decode($response);

			if ($obj) {
				if ($obj->success) {
					$return = $obj->auth_token;
				} else {
					\Tracy\Debugger::log($obj->error);
				}
			}
		}

		return $return;
	}

	protected function bail($token) {
		$response = $this->send("/api/v1/sessions", self::DELETE, array("auth_token" => $token));
		$return = false;

		if ($response) {
			$obj = json_decode($response);

			if ($obj) {
				if ($obj->success) {
					$return = true;
				} else {
					\Tracy\Debugger::log($obj->error);
				}
			}
		}

		return $return;
	}

	protected function getLocales($token) {
		$response = $this->send("/api/v1/locales", self::GET, array("auth_token" => $token));
		$return = false;

		if ($response) {
			$obj = json_decode($response);

			if ($obj) {
				$return = $obj;
			}
		}

		return $return;
	}

	protected function getFile($token, $code, $format, $tag) {
		$response = $this->send("/api/v1/translations/download", self::GET,
				array(
					"auth_token" => $token,
					"locale" => $code,
					"format" => $format,
					"tag" => $tag
				));
		$return = false;

		if ($response) {
			$return = $response;
		}

		return $return;
	}

	protected function postFile($token, $code, $content, $format, $tags, $update = false) {
		$response = $this->send("/api/v1/file_imports", self::POST,
				array(
					"auth_token" => $token,
					"file_import[locale_code]" => $code,
					"file_import[filename]" => "messages.yml",
					"file_import[file_content]" => $content,
	//				"file_import[format]" => $format,
					"file_import[tag_names]" => $tags,
					"file_import[update_translations]" => (int) $update
				));
		$return = false;

		if ($response) {
			$obj = json_decode($response);

			if ($obj) {
				if ($obj->success) {
					$return = true;
				} else {
					\Tracy\Debugger::log($obj->error);
				}
			}
		}

		return $return;
	}

	protected function postArray($token, $code, $array, $format, $tags, $update = false) {
		$dumper = new \Symfony\Component\Yaml\Yaml();

		$content = $dumper->dump($array);

		return $this->postFile($token, $code, $content, $format, $tags, $update);
	}


	////////////////////////////////////////////////////////////////////////////////////////////////////
	public function pullLocales() {
		$ok = true;
		$token = $this->authenticate(self::LOGIN, self::PASSWORD);

		if (!$token) {
			return false;
		}

		if ($locales = $this->getLocales($token)) {
			return $locales;
		} else {
			$ok = false;
		}

		return $this->bail($token) && $ok;
	}

	public function xtract(&$out = FALSE) {
		$file = WWW_DIR . "/index.php";
		$params = "config:".$_SERVER['HTTP_HOST']." kdyby:translation-extract --output-format='yml' --catalogue-language='cs'";
		$output = array();
		$result = -1;

		exec("php " . $file . " " . $params, $output, $result);

		if ($out !== FALSE) {
			$out = $output;
		}

		return !$result;
	}

	public function push() {
		$ok = true;
		$token = $this->authenticate(self::LOGIN, self::PASSWORD);

		if (!$token) {
			return false;
		}

		$filename = LANG_DIR . "/messages.cs.yml";
		$filecontent = file_get_contents($filename, "r");

		if (!$filecontent) {
			\Tracy\Debugger::log("Can not open file: " . $filename);

			$ok = false;
		}

		$ok = $ok && $this->postFile($token, "cs-CZ", $filecontent, "yml", "ADT,SUNKINS_SVETZDRAVI_KNT_WEB");

		return $this->bail($token) && $ok;
	}

	/**
	 * DB CODES!
	 * @param $array
	 * @param $langCode
	 * @return bool
	 */
	public function pushArray($array, $langCode = "cs-CZ") {
		$ok = true;
		$token = $this->authenticate(self::LOGIN, self::PASSWORD);

		if (!$token) {
			return false;
		}

		$ok = $ok && $this->postArray($token, $langCode, $array, "yml", "ADT,SUNKINS_SVETZDRAVI_KNT_WEB_DB");

		return $this->bail($token) && $ok;
	}

	public function pull() {
		$ok = true;
		$token = $this->authenticate(self::LOGIN, self::PASSWORD);

		if (!$token) {
			return false;
		}

		if ($locales = $this->getLocales($token)) {
			foreach ($locales as $locale) {
				$filecontent = $this->getFile($token, $locale->code, "simple_json", "SUNKINS_SVETZDRAVI_KNT_WEB");

				if (!$filecontent) {
					$ok = false;

					continue;
				}

				$code = preg_replace("/-../", "", $locale->code);
				$filename = LANG_DIR . "/messages." . $code . ".yml";
				$file = fopen($filename, "w");

				if (!$file) {
					\Tracy\Debugger::log("Can not open file: " . $filename);

					$ok = false;

					continue;
				}

				$array = json_decode($filecontent, true);

				$dumper = new \Symfony\Component\Yaml\Yaml();

				$filecontent2 = $dumper->dump($array);

				fwrite($file, $filecontent2);

				fclose($file);
			}
		} else {
			$ok = false;
		}

		return $this->bail($token) && $ok;
	}

	/**
	 * DB CODES!
	 * @param $locale Lang code long (cs_CZ).
	 * @return bool|\Nette\Utils\ArrayHash Array of arrays of translations.
	 */
	public function pullArray($locale) {
		$ok = true;
		$token = $this->authenticate(self::LOGIN, self::PASSWORD);

		if (!$token) {
			return false;
		}

		$arrays = new \Nette\Utils\ArrayHash();

		$filecontent = $this->getFile($token, $locale, "simple_json", "SUNKINS_SVETZDRAVI_KNT_WEB_DB");

		if (!$filecontent) {
			$ok = false;
		}

		$code = preg_replace("/-../", "", $locale);

		$arrays[$code] = json_decode($filecontent, true);

		if ($this->bail($token) && $ok) {
			return $arrays;
		}

		return false;
	}
}