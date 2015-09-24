PhraseApp - Nette Addon
============

````
Import/export of PhraseApp translations.
Upstream direction takes in account only default language and does not update translations (just adds).
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
composer require adt/phraseApp
````

Recommended config in *.neon:
````

parameters:
	phraseapp:
		appId: <yourPhraseAppId>
		appDescription: <yourCompanyOrProjectDescriptionWithEmail>
		authToken: <authorizationToken>
		defaultCode: <shortLanguageCodeForExample 'cs'>
		filesDir: '%appDir%/lang'
		tagApp: <appTagUsedForWholeCommunication>
		tagFile: <fileTagUsedForFileImportExport>
		tagArray: 'SUNKINS_SVETZDRAVI_KNT_WEB_DB'
		filePrefix: 'messages'
````