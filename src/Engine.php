<?php

/*
 * This file is part of MyAss
 */
namespace MyAss;

use Cranberry\Filesystem;
use Abraham\TwitterOAuth\TwitterOAuth;

class Engine
{
	/**
	 * @var	array
	 */
	protected $globalFilters=[];

	/**
	 * @var	ZTB\History
	 */
	protected $history;

	/**
	 * @var	Cranberry\Filesystem\File
	 */
	protected $historyFile;

	/**
	 * @var	ZTB\Corpus
	 */
	protected $verbsCorpus;

	/**
	 * @param	ZTB\History	$history
	 *
	 * @param	Cranberry\Filesystem\Directory	$corporaDirectory
	 *
	 * @return	void
	 */
	public function __construct( Filesystem\File $historyFile, Filesystem\Directory $corporaDirectory )
	{
		$this->historyFile = $historyFile;
		$this->history = History::createFromJSONEncodedFile( $historyFile );

		$this->corporaDirectory = $corporaDirectory;
		$this->verbCorpus = $this->getCorpus( 'words', 'verbs' );
	}

	/**
	 * Returns whether a given string appears in a given array of unwanted words
	 *
	 * @param	string	$string
	 *
	 * @param	array	$unwantedWords
	 *
	 * @return	bool
	 */
	static public function ___filterUnwantedWords( string $string, array $unwantedWords ) : bool
	{
		$string = strtolower( $string );
		foreach( $unwantedWords as $unwantedWord )
		{
			$pattern = sprintf( '/%s/', $unwantedWord );
			$result = preg_match( $pattern, $string );

			if( $result == 1 )
			{
				return false;
			}
		}
		return true;
	}

	/**
	 * Returns a verb
	 *
	 * @return	string
	 */
	public function getVerb() : string
	{
		$verb = $this->getRandomValueFromCorpus( $this->verbCorpus, $this->history );
		$this->history->addDomainItem( $this->verbCorpus->getName(), $verb );

		return $verb;
	}

	/**
	 * Returns Corpus object instantiated from JSON-encoded data file
	 *
	 * @param	string	$category
	 *
	 * @param	string	$corpus
	 *
	 * @param	string	$domain
	 *
	 * @throws	InvalidArgumentException	If source file not found
	 *
	 * @return	ZTB\Corpus
	 */
	public function getCorpus( string $category, string $corpus, string $domain=null ) : Corpus
	{
		$corpusFile = $this->corporaDirectory
			->getChild( $category, Filesystem\Node::DIRECTORY )
			->getChild( "{$corpus}.json", Filesystem\Node::FILE );

		if( !$corpusFile->exists() )
		{
			throw new \InvalidArgumentException( "Corpus file not found: '{$corpusFile}'" );
		}

		$corpus = Corpus::createFromJSONEncodedFile( $corpusFile, $domain );
		return $corpus;
	}

	/**
	 * Returns a random, inexhausted Corpus object from the given pool.
	 *
	 * If all Corpus objects are exhausted, reset their domains in the given
	 * History object and try again.
	 *
	 * @param	array		$corpusPool		An array of Corpus objects
	 *
	 * @param	ZTB\History	&$history		Passed by reference
	 *
	 * @return	ZTB\Corpus
	 */
	static public function getRandomCorpusFromPool( array $corpusPool, History &$history ) : Corpus
	{
		if( count( $corpusPool ) == 0 )
		{
			throw new \UnderflowException( 'Corpus pool is empty' );
		}

		shuffle( $corpusPool );
		foreach( $corpusPool as $corpus )
		{
			if( !self::isCorpusExhausted( $corpus, $history ) )
			{
				return $corpus;
			}
		}

		/* All Corpus objects are exhausted, so we'll reset all relevant domains
		   in the given History object */
		foreach( $corpusPool as $corpus )
		{
			$history->removeDomain( $corpus->getName() );
		}

		return self::getRandomCorpusFromPool( $corpusPool, $history );
	}

	/**
	 * Returns random value from given Corpus pool which passes given filters
	 *
	 * @param	array	$filterQueue
	 *
	 * @param	array	$corpusPool
	 *
	 * @return	string
	 */
	protected function getRandomFilteredStringFromCorpusPool( array $filterQueue, array $corpusPool ) : string
	{
		$filters = array_merge( $this->globalFilters, $filterQueue );

		do
		{
			$didPassAllFilters = true;
			$string = $this->getRandomValueFromCorpusPool( $corpusPool, $this->history );

			foreach( $filters as $filter )
			{
				$filterParams = $filter['params'];
				array_unshift( $filterParams, $string );

				$didPassFilter = call_user_func_array( $filter['callback'], $filterParams );
				$didPassAllFilters = $didPassAllFilters && $didPassFilter;
			}
		}
		while( $didPassAllFilters == false );

		return $string;
	}

	/**
	 * Returns a random value from the given Corpus object which is not present
	 * in the associated History domain.
	 *
	 * If all Corpus values are exhausted, reset the domain in the given History
	 * object and try again
	 *
	 * @return    string
	 */
	static public function getRandomValueFromCorpus( Corpus $corpus, History &$history ) : string
	{
		$corpusName = $corpus->getName();
		$corpusValues = $corpus->getAllItems();

		if( count( $corpusValues ) == 0 )
		{
			throw new \UnderflowException( 'Corpus is empty' );
		}

		shuffle( $corpusValues );

		foreach( $corpusValues as $corpusValueSet )
		{
			$corpusValue = $corpusValueSet['present'];
			if( !$history->hasDomainItem( $corpusName, $corpusValue ) )
			{
				return $corpusValue;
			}
		}

		/* All values are exhausted, so we'll reset the relevant domain in the
		   given History object */
		$history->removeDomain( $corpusName );

		return self::getRandomValueFromCorpus( $corpus, $history );
	}

	/**
	 * Returns a random string from the given Corpus pool.
	 *
	 * @param	array		$corpusPool		An array of Corpus objects
	 *
	 * @param	ZTB\History	$history
	 *
	 * @return	string
	 */
	static public function getRandomValueFromCorpusPool( array $corpusPool, History $history ) : string
	{
		$randomCorpus = self::getRandomCorpusFromPool( $corpusPool, $history );
		$randomValue = self::getRandomValueFromCorpus( $randomCorpus, $history );

		$history->addDomainItem( $randomCorpus->getName(), $randomValue );

		return $randomValue;
	}

	/**
	 * Finds whether all items in a corpus appear in the history
	 *
	 * @param	ZTB\Corpus	$corpus
	 *
	 * @param	ZTB\History	$history
	 *
	 * @return	bool
	 */
	static public function isCorpusExhausted( Corpus $corpus, History $history ) : bool
	{
		$corpusItems = $corpus->getAllItems();
		$corpusName = $corpus->getName();

		if( !$history->hasDomain( $corpusName ) )
		{
			return false;
		}

		$historyItems = $history->getAllDomainItems( $corpusName );

		$itemsDiff = array_diff( $corpusItems, $historyItems );

		return count( $itemsDiff ) == 0;
	}

	/**
	 * Finds whether all corpora in a pool are exhausted
	 *
	 * @param	array		$corpusPool		An array of Corpus objects
	 *
	 * @param	ZTB\History	$history
	 *
	 * @return	bool
	 */
	static public function isCorpusPoolExhausted( array $corpusPool, History $history ) : bool
	{
		$poolIsExhausted = true;

		foreach( $corpusPool as $corpus )
		{
			$poolIsExhausted = $poolIsExhausted && self::isCorpusExhausted( $corpus, $history );
		}

		return $poolIsExhausted;
	}

	/**
	 * Post to Twitter
	 *
	 * @param	string	$message
	 *
	 * @param	string	$consumerKey
	 *
	 * @param	string	$consumerSecret
	 *
	 * @param	string	$accessToken
	 *
	 * @param	string	$accessTokenSecret
	 *
	 * @return	void
	 */
	public function postMessageToTwitter( string $message, string $consumerKey, string $consumerSecret, string $accessToken, string $accessTokenSecret )
	{
		$connection = new TwitterOAuth( $consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
		$parameters = [ 'status' => $message ];

		$result = $connection->post( 'statuses/update', $parameters );
	}

	/**
	 * Pushes a filter onto the end of the given filter queue
	 *
	 * @param	array	$filterQueue
	 *
	 * @param	Callable	$filterCallback
	 *
	 * @param	array	$filterParams
	 *
	 * @return	void
	 */
	protected function registerFilter( array &$filterQueue, Callable $filterCallback, array $filterParams=[] )
	{
		$filter['callback'] = $filterCallback;
		$filter['params'] = $filterParams;

		$filterQueue[] = $filter;
	}

	/**
	 * Pushes a filter onto the end of the global filter queue
	 *
	 * @param	Callable	$filterCallback
	 *
	 * @param	array	$filterParams
	 *
	 * @return	void
	 */
	public function registerGlobalFilter( Callable $filterCallback, array $filterParams=[] )
	{
		$this->registerFilter( $this->globalFilters, $filterCallback, $filterParams );
	}

	/**
	 * Write contents of history object to file
	 *
	 * @return	void
	 */
	public function writeHistory()
	{
		$this->history->writeToFile( $this->historyFile );
	}
}
