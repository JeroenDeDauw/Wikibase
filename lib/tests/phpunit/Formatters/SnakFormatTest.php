<?php

namespace Wikibase\Lib\Tests\Formatters;

use ReflectionClass;
use Wikibase\Lib\Formatters\SnakFormat;
use Wikibase\Lib\Formatters\SnakFormatter;

/**
 * @covers \Wikibase\Lib\Formatters\SnakFormat
 * @group Wikibase
 *
 * @license GPL-2.0-or-later
 * @author Marius Hoch
 */
class SnakFormatTest extends \PHPUnit\Framework\TestCase {

	public function baseFormatProvider() {
		yield [ SnakFormatter::FORMAT_HTML, SnakFormatter::FORMAT_HTML_VERBOSE ];
		yield [ SnakFormatter::FORMAT_HTML, SnakFormatter::FORMAT_HTML_VERBOSE_PREVIEW ];
		yield [ SnakFormatter::FORMAT_WIKI, SnakFormatter::FORMAT_WIKI ];
	}

	/**
	 * @dataProvider baseFormatProvider
	 */
	public function testGetBaseFormat( $expect, $format ) {
		$snakFormatHelper = new SnakFormat();
		$this->assertSame( $expect, $snakFormatHelper->getBaseFormat( $format ) );
	}

	/**
	 * Make sure all SnakFormatter::FORMAT_* constants are known/supported
	 */
	public function testGetBaseFormat_complete() {
		$snakFormatHelper = new SnakFormat();
		$refSnakFormatter = new ReflectionClass( SnakFormatter::class );

		foreach ( $refSnakFormatter->getConstants() as $cname => $cvalue ) {
			if ( strpos( $cname, 'FORMAT_' ) !== 0 ) {
				continue;
			}
			$this->assertIsString( $snakFormatHelper->getBaseFormat( $cvalue ) );
		}
	}

	public function possibleFormatProvider() {
		yield [ true, SnakFormatter::FORMAT_HTML, SnakFormatter::FORMAT_HTML ];
		yield [ true, SnakFormatter::FORMAT_HTML, SnakFormatter::FORMAT_HTML_VERBOSE ];
		yield [ true, SnakFormatter::FORMAT_HTML, SnakFormatter::FORMAT_HTML_VERBOSE_PREVIEW ];
		yield [ true, SnakFormatter::FORMAT_HTML_VERBOSE, SnakFormatter::FORMAT_HTML_VERBOSE_PREVIEW ];
		yield [ false, SnakFormatter::FORMAT_PLAIN, SnakFormatter::FORMAT_HTML ];
	}

	/**
	 * @dataProvider possibleFormatProvider
	 */
	public function testIsPossibleFormat( $expect, $availableFormat, $targetFormat ) {
		$snakFormatHelper = new SnakFormat();
		$this->assertSame( $expect, $snakFormatHelper->isPossibleFormat( $availableFormat, $targetFormat ) );
	}

}
