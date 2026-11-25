<?php
/**
 * Standalone tests for TBTST_Format. WordPress is not loaded.
 *
 * Run: php tests/test-format.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );

require __DIR__ . '/../includes/class-tbtst-format.php';

$pass = 0;
$fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
	} else {
		$fail++;
		echo "FAIL: {$label}\n";
	}
}
function same( $actual, $expected, $label ) {
	ok( $actual === $expected, $label . ( $actual === $expected ? '' : "\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) ) );
}

/* ---------- fold() ---------- */

same( TBTST_Format::fold( '  Łódź   Ćwiczenia ' ), 'lodz cwiczenia', 'fold: Polish letters, ł, spaces' );
same( TBTST_Format::fold( 'Café NAÏVE' ), 'cafe naive', 'fold: accents and case' );

/* ---------- cef_level() ---------- */

same( TBTST_Format::cef_level( array( 'health', 'CEF B1' ) ), 'B1', 'cef_level: CEF B1' );
same( TBTST_Format::cef_level( array( 'cef  c2' ) ), 'C2', 'cef_level: cef  c2' );
same( TBTST_Format::cef_level( array( 'CEF B1+' ) ), '', 'cef_level: CEF B1+ rejected' );
same( TBTST_Format::cef_level( array( 'CEFB2' ) ), 'B2', 'cef_level: no space' );
same( TBTST_Format::cef_level( array( 'sleep', 'money' ) ), '', 'cef_level: none' );
same( TBTST_Format::cef_level( array() ), '', 'cef_level: no tags' );

/* ---------- split_tags() / sort_tags() ---------- */

same( TBTST_Format::split_tags( 'money | happiness | CEF B2' ), array( 'money', 'happiness', 'CEF B2' ), 'split_tags: pipes' );
same( TBTST_Format::split_tags( 'money, happiness, CEF B2,' ), array( 'money', 'happiness', 'CEF B2' ), 'split_tags: commas, trailing comma' );
same( TBTST_Format::split_tags( 'Sleep, sleep, SLEEP; health' ), array( 'Sleep', 'health' ), 'split_tags: duplicates keep the first spelling' );
same( TBTST_Format::split_tags( ' , ,| ;' ), array(), 'split_tags: empties' );
same( TBTST_Format::split_tags( null ), array(), 'split_tags: NULL' );
same( TBTST_Format::sort_tags( array( 'sleep', 'CEF C1', 'Health', 'ąb' ) ), array( 'ąb', 'CEF C1', 'Health', 'sleep' ), 'sort_tags: A–Z, case-insensitive' );

/* ---------- href() ---------- */

same( TBTST_Format::href( 'https://thebluetree.pl/why-we-sleep/' ), 'https://thebluetree.pl/why-we-sleep/', 'href: bare' );
same( TBTST_Format::href( 'https://multidecker.com/deck/1||<button class="">OPEN</button>' ), 'https://multidecker.com/deck/1', 'href: URL||button' );
same( TBTST_Format::href( '<a href="https://x.pl/a">Open</a>' ), 'https://x.pl/a', 'href: <a href="">' );
same( TBTST_Format::href( "<a href='https://x.pl/b'>Open</a>" ), 'https://x.pl/b', "href: <a href=''>" );
same( TBTST_Format::href( 'https://x.pl/?a=1&amp;b=2' ), 'https://x.pl/?a=1&b=2', 'href: &amp; decoded' );
same( TBTST_Format::href( null ), '', 'href: NULL' );
same( TBTST_Format::href( 'NULL' ), '', 'href: "NULL"' );
same( TBTST_Format::href( '' ), '', 'href: empty' );
same( TBTST_Format::href( '  ' ), '', 'href: spaces' );

/* ---------- normalise_url() ---------- */

same( TBTST_Format::normalise_url( 'https://www.TheBlueTree.pl/why-we-sleep/' ), 'https://thebluetree.pl/why-we-sleep', 'normalise_url: www, host case, slash' );
same( TBTST_Format::normalise_url( 'HTTP://thebluetree.pl/why-we-sleep' ), 'https://thebluetree.pl/why-we-sleep', 'normalise_url: scheme' );
same( TBTST_Format::normalise_url( 'https://thebluetree.pl/' ), 'https://thebluetree.pl', 'normalise_url: home' );
same( TBTST_Format::normalise_url( 'https://thebluetree.pl/swipe/?utm_source=x&deck=abc&fbclid=1' ), 'https://thebluetree.pl/swipe?deck=abc', 'normalise_url: drops other query keys' );
same( TBTST_Format::normalise_url( 'https://thebluetree.pl/?test=t1&p=12' ), 'https://thebluetree.pl?p=12&test=t1', 'normalise_url: kept keys sorted' );
same( TBTST_Format::normalise_url( 'https://thebluetree.pl/%C5%82%C3%B3d%C5%BA/' ), 'https://thebluetree.pl/łódź', 'normalise_url: encoded path' );
same( TBTST_Format::normalise_url( '' ), '', 'normalise_url: empty' );

/* ---------- slug_from_url() / query_post_id() ---------- */

same( TBTST_Format::slug_from_url( 'https://thebluetree.pl/we-should-all-grow-fat-and-be-happy/' ), 'we-should-all-grow-fat-and-be-happy', 'slug_from_url: trailing slash' );
same( TBTST_Format::slug_from_url( 'https://thebluetree.pl/choosing-career' ), 'choosing-career', 'slug_from_url: no trailing slash' );
same( TBTST_Format::slug_from_url( 'https://thebluetree.pl/choosing-career/?utm_source=x#top' ), 'choosing-career', 'slug_from_url: query string and fragment ignored' );
same( TBTST_Format::slug_from_url( 'https://thebluetree.pl/2019/05/6-Elevator-Pitches/' ), '6-elevator-pitches', 'slug_from_url: last segment, lower-case' );
same( TBTST_Format::slug_from_url( 'https://thebluetree.pl/%C5%BCaba/' ), 'żaba', 'slug_from_url: encoded slug decoded' );
same( TBTST_Format::slug_from_url( 'https://thebluetree.pl/?p=123' ), '', 'slug_from_url: ?p=123 has no slug' );
same( TBTST_Format::slug_from_url( 'https://thebluetree.pl' ), '', 'slug_from_url: home' );
same( TBTST_Format::query_post_id( 'https://thebluetree.pl/?p=123' ), 123, 'query_post_id: ?p=123' );
same( TBTST_Format::query_post_id( 'https://thebluetree.pl/?page_id=45&x=1' ), 45, 'query_post_id: ?page_id=45' );
same( TBTST_Format::query_post_id( 'https://thebluetree.pl/?p=abc' ), 0, 'query_post_id: not a number' );
same( TBTST_Format::query_post_id( 'https://thebluetree.pl/choosing-career/' ), 0, 'query_post_id: none' );

/* ---------- kind() ---------- */

$md = array( 'multidecker.com', 'squarewords.arturdelura.com' );
same( TBTST_Format::kind( 'https://www.multidecker.com/d/1', 'thebluetree.pl', $md ), 'multidecker', 'kind: multidecker' );
same( TBTST_Format::kind( 'http://squarewords.arturdelura.com/x', 'thebluetree.pl', $md ), 'multidecker', 'kind: old Multidecker host' );
same( TBTST_Format::kind( 'https://thebluetree.pl/wp-content/uploads/2023/05/a.docx', 'thebluetree.pl', $md ), 'file', 'kind: file in uploads' );
same( TBTST_Format::kind( 'https://polecanynauczycielangielskiego.pl/pliki/lekcja.PDF', 'thebluetree.pl', $md ), 'file', 'kind: PNA PDF' );
same( TBTST_Format::kind( 'https://thebluetree.pl/?p=1&preview=true', 'thebluetree.pl', $md ), 'draft', 'kind: draft preview' );
same( TBTST_Format::kind( 'https://www.thebluetree.pl/why-we-sleep/', 'thebluetree.pl', $md ), 'site', 'kind: site' );
same( TBTST_Format::kind( 'https://view.genial.ly/abc', 'thebluetree.pl', $md ), 'other', 'kind: other' );

/* ---------- cat_key() / match_category() ---------- */

$names = array( 'Tests and Quizzes', 'Personal productivity', 'Man and Society', 'Everyday English', 'Swipe', 'Multidecker', 'Language Games' );
same( TBTST_Format::cat_key( 'Tests &amp; Quizzes' ), TBTST_Format::cat_key( 'Tests and Quizzes' ), 'cat_key: &amp;' );
same( TBTST_Format::cat_key( ' tests & quizzes' ), 'tests and quizzes', 'cat_key: & and leading space' );
same( TBTST_Format::match_category( 'Tests &amp; Quizzes', $names ), 'Tests and Quizzes', 'match_category: &amp;' );
same( TBTST_Format::match_category( 'Tests & Quizzes', $names ), 'Tests and Quizzes', 'match_category: &' );
same( TBTST_Format::match_category( ' Personal productivity', $names ), 'Personal productivity', 'match_category: leading space' );
same( TBTST_Format::match_category( 'Man and society', $names ), 'Man and Society', 'match_category: capitals' );
same( TBTST_Format::match_category( 'Flashcard Sets', $names ), 'Multidecker', 'match_category: Flashcard Sets alias' );
same( TBTST_Format::match_category( 'games', $names ), 'Language Games', 'match_category: Games alias' );
same( TBTST_Format::match_category( 'Learn words,  Everyday English', $names ), 'Everyday English', 'match_category: first part unknown, second matches' );
same( TBTST_Format::match_category( 'Holidays', $names ), '', 'match_category: unknown' );
same( TBTST_Format::match_category( null, $names ), '', 'match_category: NULL' );
same( TBTST_Format::category_in( 'Business English,  Handouts', array( 'Handouts', 'Articles' ) ), true, 'category_in: any part' );
same( TBTST_Format::category_in( 'Business English', array( 'Handouts' ) ), false, 'category_in: none' );

/* ---------- valid_level() / lines() ---------- */

same( TBTST_Format::valid_level( 'B1' ), 'B1', 'valid_level: B1' );
same( TBTST_Format::valid_level( ' b2 ' ), 'B2', 'valid_level: b2' );
same( TBTST_Format::valid_level( 'all' ), '', 'valid_level: all' );
same( TBTST_Format::valid_level( 'NULL' ), '', 'valid_level: NULL' );
same( TBTST_Format::valid_level( null ), '', 'valid_level: null' );
same( TBTST_Format::valid_level( 'B2, B1' ), '', 'valid_level: two levels' );
same( TBTST_Format::lines( "Language Games\r\n\n  Mind-maps \nlanguage games" ), array( 'Language Games', 'Mind-maps' ), 'lines: trimmed, empties and duplicates dropped' );

echo "\n{$pass} passed, {$fail} failed\n";
exit( $fail ? 1 : 0 );
