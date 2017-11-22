<?php

/**
 * This file is part of MyAss
 */
namespace MyAss;

use Corpus;
use Cranberry\Filesystem;
use Cranberry\Shell;
use Cranberry\Shell\Input;
use Cranberry\Shell\Output;
use Cranberry\Shell\Middleware;

$___bootstrap = function( Shell\Application &$app )
{
	/*
	 * Middleware
	 */

	/**
	 * Configures Engine object based on local environment.
	 *
	 * Once the engine object is configured, it is registered as a parameter for
	 * all remaining middleware.
	 *
	 * @param	Cranberry\Shell\Input\InputInterface	$input
	 *
	 * @param	Cranberry\Shell\Output\OutputInterface	$output
	 *
	 * @return	Middleware\Middleware::CONTINUE
	 */
		$___init = function( Input\InputInterface $input, Output\OutputInterface $output )
		{
			if( !$input->hasEnv( 'MYASS_DATA' ) )
			{
				throw new \RuntimeException( sprintf( Application::ERROR_STRING_ENV, 'MYASS_DATA' ) );
			}
			if( !$input->hasEnv( 'MYASS_CORPORA' ) )
			{
				throw new \RuntimeException( sprintf( Application::ERROR_STRING_ENV, 'MYASS_CORPORA' ) );
			}

			if( !$input->hasOption( 'no-tweet' ) )
			{
				if( !$input->hasEnv( 'MYASS_TWITTER_CONSUMER_KEY' ) )
				{
					throw new \RuntimeException( sprintf( Application::ERROR_STRING_ENV, 'MYASS_TWITTER_CONSUMER_KEY' ) );
				}
				if( !$input->hasEnv( 'MYASS_TWITTER_CONSUMER_SECRET' ) )
				{
					throw new \RuntimeException( sprintf( Application::ERROR_STRING_ENV, 'MYASS_TWITTER_CONSUMER_SECRET' ) );
				}
				if( !$input->hasEnv( 'MYASS_TWITTER_ACCESS_TOKEN' ) )
				{
					throw new \RuntimeException( sprintf( Application::ERROR_STRING_ENV, 'MYASS_TWITTER_ACCESS_TOKEN' ) );
				}
				if( !$input->hasEnv( 'MYASS_TWITTER_ACCESS_SECRET' ) )
				{
					throw new \RuntimeException( sprintf( Application::ERROR_STRING_ENV, 'MYASS_TWITTER_ACCESS_SECRET' ) );
				}
			}

			/*
			 * Data directory
			 */
			$dataPathname = $input->getEnv( 'MYASS_DATA' );
			$dataDirectory = new Filesystem\Directory( $dataPathname );
			if( !$dataDirectory->exists() )
			{
				throw new \RuntimeException( sprintf( "Invalid data directory: '%s' not found", $dataDirectory->getPathname() ) );
			}
			if( !$dataDirectory->isWritable() )
			{
				throw new \RuntimeException( sprintf( "Invalid data directory: Insufficient permissions for '%s'", $dataDirectory->getPathname() ) );
			}

			/*
			 * History
			 */
			$historyFile = $dataDirectory->getChild( 'history.json', Filesystem\Node::FILE );
			if( !$historyFile->exists() )
			{
				$historyFile->putContents( '[]' );
			}

			/*
			 * Engine
			 */
			$engine = new Engine( $historyFile );
			$this->registerMiddlewareParameter( $engine );

			/*
			 * Corpora
			 */
			$corporaPathname = $input->getEnv( 'MYASS_CORPORA' );
			$corporaDirectory = new Filesystem\Directory( $corporaPathname );
			if( !$corporaDirectory->exists() )
			{
				throw new \RuntimeException( sprintf( "Invalid corpora directory: '%s' not found", $corporaDirectory->getPathname() ) );
			}

			/* words/verbs */
			$verbsCorpus = Corpus\Corpus::createFromJSONEncodedFile( $corporaDirectory->getChild( 'words/verbs.json' ), 'verbs' );
			$verbsCorpus->setItemSelector( 'present' );
			$engine->addCorpus( $verbsCorpus );

			/* instructions/laundry_care */
			$laundryCorpus = Corpus\Corpus::createFromJSONEncodedFile( $corporaDirectory->getChild( 'instructions/laundry_care.json' ), 'laundry_care_instructions' );
			$laundryCorpus->setItemSelector( 'instruction' );
			$engine->addCorpus( $laundryCorpus );

			/* words/word_clues */
			$cluesFiveSelectors = [
				'abort', 'adorn', 'agree', 'allow', 'amaze', 'amuse', 'await',
				'belch', 'boast', 'bring', 'cater', 'chafe', 'chide', 'covet',
				'decay', 'deter', 'drown', 'elect', 'erupt', 'evade', 'excel',
				'exert', 'exist', 'greet', 'grind', 'hover', 'infer', 'laugh',
				'learn', 'merge', 'panic',
			];

			foreach( $cluesFiveSelectors as $selector )
			{
				$cluesFiveCorpus = Corpus\Corpus::createFromJSONEncodedFile( $corporaDirectory->getChild( 'words/word_clues/clues_five.json' ), 'data', $selector );
				$engine->addCorpus( $cluesFiveCorpus );
			}

			/*
			 * Filters
			 */

			/* 2 words or fewer */
			$engine->registerGlobalFilter( function( $corpusItem )
			{
				return substr_count( $corpusItem, ' ' ) < 2;
			} );

			/* Unwanted words */
			$engine->registerGlobalFilter( function( $corpusItem )
			{
				$unwantedWords = [',', 'not', 'one\'s'];
				foreach( $unwantedWords as $unwantedWord )
				{
					if( substr_count( $corpusItem, $unwantedWord ) > 0 )
					{
						return false;
					}
				}

				return true;
			} );

			/* Don't end with a preposition */
			$engine->registerGlobalFilter( function ( $corpusItem )
			{
				$prepositions = ['for','from','of','to','on','up','in','out','off'];
				$words = explode( ' ', $corpusItem );
				$lastWord = array_pop( $words );

				return !in_array( $lastWord, $prepositions );
			} );

			return Middleware\Middleware::CONTINUE;
		};
		$app->pushMiddleware( new Middleware\Middleware( $___init ) );

		/**
		 * Tweets?
		 *
		 * @param	Cranberry\Shell\Input\InputInterface	$input
		 *
		 * @param	Cranberry\Shell\Output\OutputInterface	$output
		 *
		 * @param	ZTB\Engine	$engine
		 *
		 * @return	Middleware\Middleware::CONTINUE
		 */
		$___tweet = function( Input\InputInterface $input, Output\OutputInterface $output, Engine $engine )
		{
			$verb = ucwords( $engine->getVerb() );
			$message = sprintf( '%s, My Ass (I Won’t %1$s)', $verb );

			if( !$input->hasOption( 'no-tweet' ) )
			{
				$consumerKey = $input->getEnv( 'MYASS_TWITTER_CONSUMER_KEY' );
				$consumerSecret = $input->getEnv( 'MYASS_TWITTER_CONSUMER_SECRET' );
				$accessToken = $input->getEnv( 'MYASS_TWITTER_ACCESS_TOKEN' );
				$accessSecret = $input->getEnv( 'MYASS_TWITTER_ACCESS_SECRET' );

				try
				{
					$engine->postMessageToTwitter( $message, $consumerKey, $consumerSecret, $accessToken, $accessSecret );
				}
				catch( \Exception $e )
				{
					throw new \RuntimeException( $e->getMessage(), 1, $e );
				}
			}
			else
			{
				$output->write( $message . PHP_EOL );
			}

			$engine->writeHistory();
		};
		$app->pushMiddleware( new Middleware\Middleware( $___tweet ) );

		/*
		 * Error Middleware
		 */
		$___runtime = function( Input\InputInterface $input, Output\OutputInterface $output, \RuntimeException $exception )
		{
			$output->write( sprintf( '%s: %s', $this->getName(), $exception->getMessage() ) . PHP_EOL );
		};
		$app->pushErrorMiddleware( new Middleware\Middleware( $___runtime, \RuntimeException::class ) );
};
