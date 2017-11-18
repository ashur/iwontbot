<?php

/**
 * This file is part of MyAss
 */
namespace MyAss;

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

			/* Corpora */
			$corporaPathname = $input->getEnv( 'MYASS_CORPORA' );
			$corporaDirectory = new Filesystem\Directory( $corporaPathname );
			if( !$corporaDirectory->exists() )
			{
				throw new \RuntimeException( sprintf( "Invalid corpora directory: '%s' not found", $corporaDirectory->getPathname() ) );
			}

			/* Data directory */
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

			/* History */
			$historyFile = $dataDirectory->getChild( 'history.json', Filesystem\Node::FILE );
			if( !$historyFile->exists() )
			{
				$historyFile->putContents( '[]' );
			}

			/* Engine */
			$engine = new Engine( $historyFile, $corporaDirectory );
			$this->registerMiddlewareParameter( $engine );

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
