<?php

/*
 * This file is part of MyAss
 */
namespace MyAss;

use Corpus;
use Cranberry\Filesystem;
use Abraham\TwitterOAuth\TwitterOAuth;

class Engine
{
	/**
	 * @var	Corpus\Pool
	 */
	protected $corpusPool;

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
	 * @param	Cranberry\Filesystem\File	$historyFile
	 *
	 * @return	void
	 */
	public function __construct( Filesystem\File $historyFile )
	{
		$this->historyFile = $historyFile;
		$this->history = Corpus\History::createFromJSONEncodedFile( $historyFile );

		$this->corpusPool = new Corpus\Pool();
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
	 * Adds a Corpus object to the Pool
	 *
	 * @param	Corpus\Corpus	$corpus
	 *
	 * @return	void
	 */
	public function addCorpus( Corpus\Corpus $corpus ) : void
	{
		$this->corpusPool->addCorpus( $corpus );
	}

	/**
	 * Returns a verb
	 *
	 * @return	string
	 */
	public function getVerb() : string
	{
		return $this->getRandomFilteredItem();
	}

	/**
	 * Returns random value from given Corpus pool which passes given filters
	 *
	 * @param	array	$filterQueue
	 *
	 * @return	string
	 */
	protected function getRandomFilteredItem( array $filterQueue=[] ) : string
	{
		$filters = array_merge( $this->globalFilters, $filterQueue );

		do
		{
			$didPassAllFilters = true;
			$corpusItem = $this->corpusPool->getRandomItem( $this->history );
			$corpusItem = strtolower( $corpusItem );

			foreach( $filters as $filter )
			{
				$filterParams = $filter['params'];
				array_unshift( $filterParams, $corpusItem );

				$didPassFilter = call_user_func_array( $filter['callback'], $filterParams );
				$didPassAllFilters = $didPassAllFilters && $didPassFilter;
			}
		}
		while( $didPassAllFilters == false );

		return $corpusItem;
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
