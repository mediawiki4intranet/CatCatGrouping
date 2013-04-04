<?php

/**
 * CatCatGrouping MediaWiki extension
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

/**
 * WARNING: This extension DEPENDS ON CategoryTree extension.
 */

$wgExtensionCredits['parserhook'][] = array(
    'path' => __FILE__,
    'name' => 'CatCatGrouping',
    'author' => 'Vitaliy Filippov',
    'url' => 'https://wiki.4intra.net/CatCatGrouping',
    'description' => 'Adds new mode for displaying categories - pages are grouped'.
        ' by other categories they belong to. Triggered by __CATEGORYSUBCATLIST__ or configured globally.',
);

$wgExtensionMessagesFiles['CatCatGrouping'] = dirname(__FILE__).'/CatCatGrouping.i18n.php';
$wgAutoloadClasses['CatCatGroupingCategoryPage'] = dirname(__FILE__).'/CatCatGrouping.class.php';
$wgHooks['MagicWordwgVariableIDs'][] = 'efCCGMagicWordwgVariableIDs';
$wgHooks['OutputPageParserOutput'][] = 'efCCGOutputPageParserOutput';
$wgHooks['ParserBeforeInternalParse'][] = 'efCCGParserBeforeInternalParse';
$wgHooks['ArticleFromTitle'][] = 'efCCGArticleFromTitle';
$wgExtensionFunctions[] = 'efCCGInit';

// Configuration defaults

// If true, category-grouped list is enabled by default,
// unless __NOCATEGORYSUBCATLIST__ is present on the category page.
// If false, it will be disabled by default, unless __CATEGORYSUBCATLIST__
// is present on the category page.
$wgCategorySubcategorizedList = true;

// Group adjacent subcategories having just a single page inside or not
$wgCategoryGroupCharacters = true;

// If there are more than 10 pages in a category that don't have at least
// one more category associated, show normal alpha-list.
$wgMinUncatPagesAlphaList = 10;

// These categories will be treated as "general" and always excluded from
// subclassification on category pages (just names, without 'Category:').
$wgSubcategorizedAlwaysExclude = array();

function efCCGInit()
{
    global $wgHooks;
    $n = count($wgHooks['ArticleFromTitle']);
    for ($i = $n-1; $i >= 0; $i--)
    {
        if ($wgHooks['ArticleFromTitle'][$i] == 'efCategoryTreeArticleFromTitle')
        {
            array_splice($wgHooks['ArticleFromTitle'], $i, 1);
        }
    }
}

function efCCGMagicWordwgVariableIDs(&$wgVariableIDs)
{
    wfLoadExtensionMessages('CatCatGrouping');
    $wgVariableIDs[] = 'nocategorysubcatlist';
    $wgVariableIDs[] = 'nocategorycolumns';
    return true;
}

function efCCGOutputPageParserOutput(&$out, $parserOutput)
{
    if (isset($parserOutput->useSubcategorizedList))
    {
        $out->useSubcategorizedList = $parserOutput->useSubcategorizedList;
    }
    if (isset($parserOutput->noCategoryColumns))
    {
        $out->noCategoryColumns = $parserOutput->noCategoryColumns;
    }
    return true;
}

function efCCGParserBeforeInternalParse($parser, &$text, $stripState)
{
    if (MagicWord::get('nocategorysubcatlist')->matchAndRemove($text))
    {
        $parser->mOutput->useSubcategorizedList = FALSE;
    }
    if (MagicWord::get('categorysubcatlist')->matchAndRemove($text))
    {
        $parser->mOutput->useSubcategorizedList = TRUE;
    }
    if (MagicWord::get('nocategorycolumns')->matchAndRemove($text))
    {
        $parser->mOutput->noCategoryColumns = TRUE;
    }
    return true;
}

function efCCGArticleFromTitle($title, &$article)
{
    if ($title->getNamespace() == NS_CATEGORY)
    {
        $article = new CatCatGroupingCategoryPage($title);
    }
    return true;
}
