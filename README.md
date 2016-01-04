PhraseApp - Nette Addon
============

````
Import/export of PhraseApp translations.
Upstream push takes in account only default language.
Upstream push does not update translations (just adds).
````

Installation via composer
------------

Add to your composer.json
````
"repositories": [
  {
    "type": "git",
    "url": "https://github.com/AppsDevTeam/PhraseApp.git"
  }
]
````

````
composer require adt/phraseapp
````

Recommended config in *.neon:

````
parameters:
	phraseapp:
		appId: <yourPhraseAppId>
		appDescription: <yourCompanyOrProjectDescriptionWithEmail>
		authToken: <authorizationToken>
		defaultCode: <shortLanguageCodeForExample 'cs'>

services:
	- ADT\PhraseApp(%phraseapp%)
````
