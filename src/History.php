<?php

/*
 * This file is part of MyAss
 */
namespace MyAss;

use Cranberry\Filesystem\File;

class History
{
	/**
	 * @var	array
	 */
	protected $data=[];

	/**
	 * @param	array	$data
	 *
	 * @return	void
	 */
	public function __construct( array $data=[] )
	{
		$this->data = $data;
	}

	/**
	 * Adds a value to the specified domain
	 *
	 * @param	string	$domainName
	 *
	 * @param	string	$itemValue
	 *
	 * @return	void
	 */
	public function addDomainItem( string $domainName, string $itemValue )
	{
		if( !$this->hasDomainItem( $domainName, $itemValue ) )
		{
			$this->data[$domainName][] = $itemValue;
		}
	}

	/**
	 * Factory method for creating History object using a file containing
	 * encoded JSON data
	 *
	 * @param	Cranberry\Filesystem\File	$file
	 *
	 * @throws	InvalidArgumentException	If file contents cannot be decoded
	 *
	 * @return	ZTB\History
	 */
	static public function createFromJSONEncodedFile( File $file ) : self
	{
		$data = json_decode( $file->getContents(), true );
		if( json_last_error() != JSON_ERROR_NONE )
		{
			throw new \InvalidArgumentException( "Could not decode '{$file}': " . json_last_error_msg(), json_last_error() );
		}

		return new self( $data );
	}

	/**
	 * Returns array of all items in domain
	 *
	 * @param	string	$domainName
	 *
	 * @return	array
	 */
	public function getAllDomainItems( string $domainName ) : array
	{
		if( !$this->hasDomain( $domainName ) )
		{
			throw new \DomainException( "Unknown domain '{$domainName}'" );
		}

		return $this->data[$domainName];
	}

	/**
	 * Finds whether a given domain is defined
	 *
	 * @param	string	$domainName
	 *
	 * @return	bool
	 */
	public function hasDomain( string $domainName ) : bool
	{
		return array_key_exists( $domainName, $this->data );
	}

	/**
	 * Finds whether a domain contains a given value
	 *
	 * @param	string	$domainName
	 *
	 * @param	string	$value
	 *
	 * @return	bool
	 */
	public function hasDomainItem( string $domainName, string $value )
	{
		if( !$this->hasDomain( $domainName ) )
		{
			return false;
		}

		return in_array( $value, $this->data[$domainName] );
	}

	/**
	 * Removes specified domain from history data set
	 *
	 * @param	string	$domainName
	 *
	 * @throws	DomainException	If domain is undefined
	 *
	 * @return	void
	 */
	public function removeDomain( $domainName )
	{
		if( !$this->hasDomain( $domainName ) )
		{
			throw new \DomainException( "Unknown domain '{$domainName}'" );
		}

		unset( $this->data[$domainName] );
	}

	/**
	 * Write data to file encoded as non-prettified JSON
	 *
	 * @param	Cranberry\Filesystem\File	$historyFile
	 *
	 * @return	void
	 */
	public function writeToFile( File $file )
	{
		$encodedData = json_encode( $this->data );
		$file->putContents( $encodedData );
	}
}
