<?php

class CatCatGroupingCategoryPage extends CategoryTreeCategoryPage
{
    protected $mCategoryViewerClass = 'CatCatGroupingCategoryViewer';
}

class CatCatGroupingCategoryViewer extends CategoryTreeCategoryViewer
{
    /**
     * Format a list of articles chunked by letter, either as a
     * bullet list or a columnar format, depending on the length.
     *
     * @param $articles Array
     * @param $articles_start_char Array
     * @param $cutoff Int
     * @return String
     * @private
     */
    function formatList($articles, $articles_start_char, $cutoff = 6)
    {
        global $wgOut;
        if (!isset($wgOut->noCategoryColumns) && count($articles) > $cutoff)
            return $this->columnList($articles, $articles_start_char);
        elseif ($articles)
        {
            // for short lists of articles in categories.
            return $this->shortList($articles, $articles_start_char);
        }
        return '';
    }

    function addPage($title, $sortkey, $pageLength, $isRedirect = false)
    {
        if (!$title->quickUserCan('read'))
        {
            return;
        }
        $this->titles[] = $title;
        parent::addPage($title, $sortkey, $pageLength, $isRedirect);
    }

    function getAllParentCategories($dbr, $title)
    {
        $supercats = array($title->getDBkey());
        $sch = array($title->getDBkey() => true);
        while ($supercats)
        {
            $res = $dbr->select(array('categorylinks', 'page'), 'cl_to', array(
                'cl_from=page_id',
                'page_namespace' => NS_CATEGORY,
                'page_title' => $supercats,
            ), __METHOD__, array('GROUP BY' => 'cl_to'));
            $supercats = array();
            while ($row = $dbr->fetchRow($res))
            {
                if (!isset($sch[$row[0]]))
                {
                    $supercats[] = $row[0];
                    $sch[$row[0]] = true;
                }
            }
            $dbr->freeResult($res);
        }
        return array_keys($sch);
    }

    static function columnList($items, $start_char)
    {
        global $wgCategoryGroupCharacters;
        /* If all $start_char's are more than 1-character strings,
           or if grouping is disabled through config, return normal list */
        if (!$items || mb_strlen($start_char[0]) > 1 || !$wgCategoryGroupCharacters)
            return parent::columnList($items, $start_char);
        $n = count($items);
        for ($i = 0; $i < $n-1 && mb_strlen($start_char[$i+1]) == 1; $i++)
        {
            /* Group adjacent 1-char subtitles having only 1 item
               with first subtitle having more than 1 item */
            $s = $i;
            while ($i < $n-1 && mb_strlen($start_char[$i+1]) == 1 &&
                $start_char[$i] != $start_char[$i+1] &&
                /* Don't group characters of different length */
                strlen($start_char[$i]) == strlen($start_char[$i+1]))
                $i++;
            $e = $i;
            while ($i < $n-1 && $start_char[$i] == $start_char[$i+1])
                $i++;
            /* Group last 1-char subtitle also */
            if ($e == $s && mb_strlen($start_char[$i+1]) == 1 &&
                ($i == $n-2 || mb_strlen($start_char[$i+2]) > 1))
                $e = ++$i;
            if ($e > $s)
            {
                $key = $start_char[$s] . '-' . $start_char[$e];
                for ($j = $s; $j <= $i; $j++)
                    $start_char[$j] = $key;
            }
        }
        return parent::columnList($items, $start_char);
    }

    function getPagesSection()
    {
        global $wgMinUncatPagesAlphaList;
        global $wgCategorySubcategorizedList;
        global $wgSubcategorizedAlwaysExclude;
        global $wgOut;
        if (!isset($wgOut->useSubcategorizedList))
            $wgOut->useSubcategorizedList = false;
        /* If there are no articles, or if we are forced to show normal list - show it */
        if (!$this->articles || !$wgCategorySubcategorizedList && !$wgOut->useSubcategorizedList ||
            $wgCategorySubcategorizedList && !is_null($wgOut->useSubcategorizedList) &&
            !$wgOut->useSubcategorizedList)
            return parent::getPagesSection();
        $ids = array();
        foreach ($this->titles as $t)
            $ids[] = $t->getArticleID();
        $dbr = wfGetDB(DB_SLAVE);
        /* Exclude all parent categories */
        $supercats = $this->getAllParentCategories($dbr, $this->title);
        /* Always exclude "special" categories, marked with
           one of $wgSubcategorizedAlwaysExclude. */
        if (is_array($wgSubcategorizedAlwaysExclude))
            foreach ($wgSubcategorizedAlwaysExclude as $v)
                $supercats[] = str_replace(' ', '_', $v);
        $where = array('cl_from' => $ids);
        foreach ($supercats as $k)
            $where[] = 'cl_to!='.$dbr->addQuotes($k);
        $res = $dbr->select('categorylinks', '*', $where, __METHOD__, array('ORDER BY' => 'cl_sortkey'));
        $cl = array();
        foreach ($res as $row)
            $cl[$row->cl_to][] = $row->cl_from;
        /* Make subcategorized article and subtitle list */
        $new = array();
        $newkey = array();
        $done = array();
        $ids = array_flip($ids);
        foreach ($cl as $cat => $list)
        {
            $cat = str_replace('_', ' ', $cat);
            foreach ($list as $a)
            {
                $new[] = $this->articles[$ids[$a]];
                $newkey[] = $cat;
                $done[$ids[$a]] = true;
            }
        }
        /* Count unsubcategorized articles */
        $count_undone = 0;
        for ($i = count($this->articles)-1; $i >= 0; $i--)
            if (!isset($done[$i]))
                $count_undone++;
        $cutoff = $wgMinUncatPagesAlphaList;
        if (!$cutoff || $cutoff < 0)
            $cutoff = 10;
        /* If there is less than $cutoff, show them all with
           current category subtitle, else show normal alpha-list. */
        for ($i = count($this->articles)-1; $i >= 0; $i--)
        {
            if (!isset($done[$i]))
            {
                array_unshift($new, $this->articles[$i]);
                if ($count_undone > $cutoff)
                    array_unshift($newkey, $this->articles_start_char[$i]);
                else
                    array_unshift($newkey, $this->title->getText());
            }
        }
        /* Replace article and subtitle list and call parent */
        $this->articles = $new;
        $this->articles_start_char = $newkey;
        $html = parent::getPagesSection();
        return $html;
    }

    /* Short list without subtitles, if not called from $this->getPagesSection() */
    static function shortList($articles, $articles_start_char)
    {
        global $wgMinUncatPagesAlphaList;
        $cutoff = $wgMinUncatPagesAlphaList;
        if (!$cutoff || $cutoff < 0)
            $cutoff = 10;
        if (count($articles) >= $cutoff)
            return parent::shortList($articles, $articles_start_char);
        $r = '<ul>';
        foreach ($articles as $a)
            $r .= "<li>$a</li>";
        $r .= '</ul>';
        return $r;
    }
}
